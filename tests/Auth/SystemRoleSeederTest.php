<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\Permission;
use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\RoleSlug;
use Nexis\Auth\SystemRoleSeeder;
use Nexis\Auth\SystemRoleTemplates;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class SystemRoleSeederTest extends TestCase
{
    private PDO $pdo;
    private SystemRoleSeeder $seeder;
    private PdoPermissionLookup $permissions;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE sites (id TEXT PRIMARY KEY)',
        );
        $this->pdo->exec(
            'CREATE TABLE roles (
                id TEXT PRIMARY KEY,
                site_id TEXT NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL,
                is_system INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE permissions (
                id TEXT PRIMARY KEY,
                `key` TEXT NOT NULL UNIQUE
            )',
        );
        $this->pdo->exec(
            'CREATE TABLE role_permissions (
                role_id TEXT NOT NULL,
                permission_id TEXT NOT NULL,
                PRIMARY KEY (role_id, permission_id)
            )',
        );

        $this->permissions = new PdoPermissionLookup($this->pdo);
        $this->seeder = new SystemRoleSeeder($this->pdo, $this->permissions);
    }

    public function testEnsureForSiteCreatesRedakteurAndSeoTemplates(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $this->pdo->prepare('INSERT INTO sites (id) VALUES (:id)')->execute(['id' => $siteId->value]);

        $memberId = $this->seeder->ensureForSite($siteId);
        self::assertNotSame('', $memberId);

        $roles = [];
        $stmt = $this->pdo->prepare('SELECT slug, name FROM roles WHERE site_id = :site_id ORDER BY slug');
        $stmt->execute(['site_id' => $siteId->value]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roles[(string) $row['slug']] = (string) $row['name'];
        }

        self::assertSame('Website-Admin', $roles[RoleSlug::ADMIN] ?? null);
        self::assertSame('Redakteur', $roles[RoleSlug::EDITOR] ?? null);
        self::assertSame('SEO', $roles[RoleSlug::SEO] ?? null);
        self::assertSame('Mitglied', $roles[RoleSlug::MEMBER] ?? null);

        $editorId = $this->roleId($siteId, RoleSlug::EDITOR);
        $seoId = $this->roleId($siteId, RoleSlug::SEO);
        self::assertNotNull($editorId);
        self::assertNotNull($seoId);

        foreach (Permission::editorDefaults() as $key) {
            self::assertTrue($this->roleHas($editorId, $key), $key);
        }
        self::assertFalse($this->roleHas($editorId, Permission::CONTENT_PAGE_PUBLISH));
        self::assertFalse($this->roleHas($editorId, Permission::THEME_MANAGE));

        foreach (Permission::seoDefaults() as $key) {
            self::assertTrue($this->roleHas($seoId, $key), $key);
        }
        self::assertFalse($this->roleHas($seoId, Permission::CONTENT_PAGE_EDIT));
    }

    public function testSeoReceivesRedirectsWhenPermissionExists(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $this->pdo->prepare('INSERT INTO sites (id) VALUES (:id)')->execute(['id' => $siteId->value]);
        $this->permissions->ensurePermissions(['redirects.manage']);

        $this->seeder->ensureForSite($siteId);
        $seoId = $this->roleId($siteId, RoleSlug::SEO);
        self::assertNotNull($seoId);
        self::assertTrue($this->roleHas($seoId, 'redirects.manage'));
        self::assertSame(['redirects.manage'], SystemRoleTemplates::seoPluginExtras());
    }

    public function testRenamesLegacyRedaktionToRedakteur(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $this->pdo->prepare('INSERT INTO sites (id) VALUES (:id)')->execute(['id' => $siteId->value]);
        $editorId = Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $this->pdo->prepare(
            'INSERT INTO roles (id, site_id, name, slug, is_system, created_at, updated_at)
             VALUES (:id, :site_id, :name, :slug, 1, :created_at, :updated_at)',
        )->execute([
            'id' => $editorId,
            'site_id' => $siteId->value,
            'name' => 'Redaktion',
            'slug' => RoleSlug::EDITOR,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->seeder->ensureForSite($siteId);
        $stmt = $this->pdo->prepare('SELECT name FROM roles WHERE id = :id');
        $stmt->execute(['id' => $editorId]);
        self::assertSame('Redakteur', $stmt->fetchColumn());
    }

    private function roleId(SiteId $siteId, string $slug): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM roles WHERE site_id = :site_id AND slug = :slug LIMIT 1',
        );
        $stmt->execute(['site_id' => $siteId->value, 'slug' => $slug]);
        $id = $stmt->fetchColumn();

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function roleHas(string $roleId, string $key): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id AND p.`key` = :key LIMIT 1',
        );
        $stmt->execute(['role_id' => $roleId, 'key' => $key]);

        return $stmt->fetchColumn() !== false;
    }
}
