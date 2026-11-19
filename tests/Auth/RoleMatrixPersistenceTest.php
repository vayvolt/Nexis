<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\Permission;
use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\RoleSlug;
use Nexis\Auth\SystemRoleSeeder;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class RoleMatrixPersistenceTest extends TestCase
{
    private PDO $pdo;
    private SystemRoleSeeder $seeder;
    private PdoPermissionLookup $permissions;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE sites (id TEXT PRIMARY KEY)');
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

    public function testCustomEditorGrantSurvivesEnsureForSite(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $this->pdo->prepare('INSERT INTO sites (id) VALUES (:id)')->execute(['id' => $siteId->value]);
        $this->seeder->ensureForSite($siteId);

        $editorId = $this->roleId($siteId, RoleSlug::EDITOR);
        self::assertNotNull($editorId);
        self::assertFalse($this->roleHas($editorId, Permission::SETTINGS_MANAGE));

        $this->permissions->grantRole($editorId, [Permission::SETTINGS_MANAGE]);
        self::assertTrue($this->roleHas($editorId, Permission::SETTINGS_MANAGE));

        $this->seeder->ensureForSite($siteId);
        self::assertTrue($this->roleHas($editorId, Permission::SETTINGS_MANAGE));
        self::assertFalse($this->roleHas($editorId, Permission::USERS_MANAGE));
    }

    public function testResetToTemplatesRestoresEditorDefaults(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $this->pdo->prepare('INSERT INTO sites (id) VALUES (:id)')->execute(['id' => $siteId->value]);
        $this->seeder->ensureForSite($siteId);
        $editorId = $this->roleId($siteId, RoleSlug::EDITOR);
        self::assertNotNull($editorId);

        $this->permissions->grantRole($editorId, [Permission::SETTINGS_MANAGE, Permission::USERS_MANAGE]);
        $this->seeder->resetToTemplates($siteId);

        self::assertFalse($this->roleHas($editorId, Permission::SETTINGS_MANAGE));
        self::assertFalse($this->roleHas($editorId, Permission::USERS_MANAGE));
        foreach (Permission::editorDefaults() as $key) {
            self::assertTrue($this->roleHas($editorId, $key), $key);
        }
    }

    public function testEnsurePermissionsReportsOnlyNewKeys(): void
    {
        $first = $this->permissions->ensurePermissions(['forms.manage', 'consent.manage']);
        sort($first);
        self::assertSame(['consent.manage', 'forms.manage'], $first);

        $second = $this->permissions->ensurePermissions(['forms.manage', 'consent.manage', 'blog.manage']);
        self::assertSame(['blog.manage'], $second);

        $third = $this->permissions->ensurePermissions(['forms.manage']);
        self::assertSame([], $third);
    }

    public function testSetRolePermissionsReplacesGrants(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $this->pdo->prepare('INSERT INTO sites (id) VALUES (:id)')->execute(['id' => $siteId->value]);
        $this->seeder->ensureForSite($siteId);
        $editorId = $this->roleId($siteId, RoleSlug::EDITOR);
        self::assertNotNull($editorId);

        $this->permissions->setRolePermissions($editorId, [Permission::CONTENT_PAGE_EDIT, Permission::SETTINGS_MANAGE]);
        $keys = $this->permissions->keysForRole($editorId);
        sort($keys);
        self::assertSame([Permission::CONTENT_PAGE_EDIT, Permission::SETTINGS_MANAGE], $keys);
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
