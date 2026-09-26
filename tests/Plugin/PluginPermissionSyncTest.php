<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\UserRepository;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class PluginPermissionSyncTest extends TestCase
{
    use PluginKernelTestFactory;

    private PDO $pdo;
    private PdoPermissionLookup $permissions;
    private SiteId $siteId;
    private string $adminRoleId;
    private string $editorRoleId;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE roles (
                id TEXT PRIMARY KEY,
                site_id TEXT NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL
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

        $this->siteId = new SiteId(Uuid::v7());
        $this->adminRoleId = Uuid::v7();
        $this->editorRoleId = Uuid::v7();
        $this->pdo->prepare(
            'INSERT INTO roles (id, site_id, name, slug) VALUES (:id, :site_id, :name, :slug)',
        )->execute([
            'id' => $this->adminRoleId,
            'site_id' => $this->siteId->value,
            'name' => 'Admin',
            'slug' => 'admin',
        ]);
        $this->pdo->prepare(
            'INSERT INTO roles (id, site_id, name, slug) VALUES (:id, :site_id, :name, :slug)',
        )->execute([
            'id' => $this->editorRoleId,
            'site_id' => $this->siteId->value,
            'name' => 'Editor',
            'slug' => 'editor',
        ]);

        $this->permissions = new PdoPermissionLookup($this->pdo);
    }

    public function testRegisterPermissionsSyncGrantsAdminAndEditor(): void
    {
        $kernel = $this->makePluginKernel();
        $kernel->registerPermissions(['workshop.manage', '  ', 'workshop.manage'], ['admin', 'editor']);

        $users = $this->createStub(UserRepository::class);
        $users->method('findRoleIdBySlug')->willReturnCallback(
            function (SiteId $siteId, string $slug): ?string {
                self::assertTrue($siteId->equals($this->siteId));

                return match ($slug) {
                    'admin' => $this->adminRoleId,
                    'editor' => $this->editorRoleId,
                    default => null,
                };
            },
        );

        $kernel->syncPermissions($this->permissions, $users, $this->siteId);

        self::assertTrue($this->roleHas($this->adminRoleId, 'workshop.manage'));
        self::assertTrue($this->roleHas($this->editorRoleId, 'workshop.manage'));
        $countStmt = $this->pdo->query('SELECT COUNT(*) FROM permissions WHERE `key` = \'workshop.manage\'');
        self::assertNotFalse($countStmt);
        $count = (int) $countStmt->fetchColumn();
        self::assertSame(1, $count);
    }

    public function testRevokeForSiteRemovesGrantsOnlyForThatSite(): void
    {
        $otherSite = new SiteId(Uuid::v7());
        $otherAdmin = Uuid::v7();
        $this->pdo->prepare(
            'INSERT INTO roles (id, site_id, name, slug) VALUES (:id, :site_id, :name, :slug)',
        )->execute([
            'id' => $otherAdmin,
            'site_id' => $otherSite->value,
            'name' => 'Admin',
            'slug' => 'admin',
        ]);

        $this->permissions->grantRole($this->adminRoleId, ['workshop.manage']);
        $this->permissions->grantRole($this->editorRoleId, ['workshop.manage']);
        $this->permissions->grantRole($otherAdmin, ['workshop.manage']);

        $this->permissions->revokeForSite($this->siteId, ['workshop.manage']);

        self::assertFalse($this->roleHas($this->adminRoleId, 'workshop.manage'));
        self::assertFalse($this->roleHas($this->editorRoleId, 'workshop.manage'));
        self::assertTrue($this->roleHas($otherAdmin, 'workshop.manage'));
    }

    private function roleHas(string $roleId, string $key): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM role_permissions rp
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id AND p.`key` = :key
             LIMIT 1',
        );
        $stmt->execute(['role_id' => $roleId, 'key' => $key]);

        return $stmt->fetchColumn() !== false;
    }
}
