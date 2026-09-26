<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;

final class PdoPermissionLookup implements PermissionLookup
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function userHas(UserId $userId, SiteId $siteId, string $permission): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM site_memberships m
             INNER JOIN role_permissions rp ON rp.role_id = m.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE m.user_id = :user_id AND m.site_id = :site_id AND p.`key` = :permission
             LIMIT 1',
        );
        $stmt->execute([
            'user_id' => $userId->value,
            'site_id' => $siteId->value,
            'permission' => $permission,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function forUserOnSite(UserId $userId, SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.`key`
             FROM site_memberships m
             INNER JOIN role_permissions rp ON rp.role_id = m.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE m.user_id = :user_id AND m.site_id = :site_id
             ORDER BY p.`key` ASC',
        );
        $stmt->execute([
            'user_id' => $userId->value,
            'site_id' => $siteId->value,
        ]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            if (is_string($key) && $key !== '') {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * @param list<string> $keys
     */
    public function ensurePermissions(array $keys): void
    {
        $sql = $this->insertIgnore(
            'permissions',
            'id, `key`',
            ':id, :key',
        );
        $stmt = $this->pdo->prepare($sql);
        foreach ($keys as $key) {
            $key = trim($key);
            if ($key === '') {
                continue;
            }
            $stmt->execute([
                'id' => Uuid::v7(),
                'key' => $key,
            ]);
        }
    }

    /**
     * @param list<string> $keys
     */
    public function grantRole(string $roleId, array $keys): void
    {
        if ($keys === []) {
            return;
        }
        $this->ensurePermissions($keys);
        $find = $this->pdo->prepare('SELECT id FROM permissions WHERE `key` = :key LIMIT 1');
        $link = $this->pdo->prepare(
            $this->insertIgnore(
                'role_permissions',
                'role_id, permission_id',
                ':role_id, :permission_id',
            ),
        );
        foreach ($keys as $key) {
            $find->execute(['key' => $key]);
            $permissionId = $find->fetchColumn();
            if (!is_string($permissionId) || $permissionId === '') {
                continue;
            }
            $link->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /**
     * @param list<string> $keys
     */
    public function revokeRole(string $roleId, array $keys): void
    {
        $normalized = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key);
            if ($key !== '') {
                $normalized[] = $key;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return;
        }

        $find = $this->pdo->prepare('SELECT id FROM permissions WHERE `key` = :key LIMIT 1');
        $unlink = $this->pdo->prepare(
            'DELETE FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id',
        );
        foreach ($normalized as $key) {
            $find->execute(['key' => $key]);
            $permissionId = $find->fetchColumn();
            if (!is_string($permissionId) || $permissionId === '') {
                continue;
            }
            $unlink->execute([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    /**
     * Remove permission grants for all roles belonging to the site.
     * Permission key rows remain (may still be used by other sites).
     *
     * @param list<string> $keys
     */
    public function revokeForSite(SiteId $siteId, array $keys): void
    {
        $normalized = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key);
            if ($key !== '') {
                $normalized[] = $key;
            }
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return;
        }

        $rolesStmt = $this->pdo->prepare('SELECT id FROM roles WHERE site_id = :site_id');
        $rolesStmt->execute(['site_id' => $siteId->value]);
        $roleIds = [];
        foreach ($rolesStmt->fetchAll(PDO::FETCH_COLUMN) as $roleId) {
            if (is_string($roleId) && $roleId !== '') {
                $roleIds[] = $roleId;
            }
        }
        if ($roleIds === []) {
            return;
        }

        $find = $this->pdo->prepare('SELECT id FROM permissions WHERE `key` = :key LIMIT 1');
        $unlink = $this->pdo->prepare(
            'DELETE FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id',
        );
        foreach ($normalized as $key) {
            $find->execute(['key' => $key]);
            $permissionId = $find->fetchColumn();
            if (!is_string($permissionId) || $permissionId === '') {
                continue;
            }
            foreach ($roleIds as $roleId) {
                $unlink->execute([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    private function insertIgnore(string $table, string $columns, string $values): string
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            return "INSERT OR IGNORE INTO {$table} ({$columns}) VALUES ({$values})";
        }

        return "INSERT IGNORE INTO {$table} ({$columns}) VALUES ({$values})";
    }
}
