<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;

/**
 * Ensures out-of-the-box system roles and their default permission grants for a site.
 */
final class SystemRoleSeeder
{
    public function __construct(
        private PDO $pdo,
        private PdoPermissionLookup $permissions,
    ) {
    }

    /**
     * Create/align admin, Redakteur, SEO, Mitglied. Returns the member role id.
     */
    public function ensureForSite(SiteId $siteId): string
    {
        $this->permissions->ensurePermissions(Permission::core());

        $memberId = '';
        foreach (SystemRoleTemplates::all() as $template) {
            $roleId = $this->ensureRole($siteId, $template['slug'], $template['name']);
            if ($template['permissions'] !== []) {
                $this->permissions->grantRole($roleId, $template['permissions']);
            }

            if ($template['slug'] === RoleSlug::EDITOR) {
                $this->permissions->revokeRole($roleId, [
                    Permission::CONTENT_PAGE_PUBLISH,
                    Permission::THEME_MANAGE,
                    Permission::THEME_CUSTOM_CSS,
                    Permission::AUDIT_VIEW,
                    Permission::PLUGIN_MANAGE,
                    Permission::PLUGIN_INSTALL,
                    Permission::SETTINGS_MANAGE,
                    Permission::USERS_MANAGE,
                    Permission::WEBHOOKS_MANAGE,
                    Permission::EXPORT_MANAGE,
                ]);
            }

            if ($template['slug'] === RoleSlug::SEO) {
                $extras = [];
                foreach (SystemRoleTemplates::seoPluginExtras() as $key) {
                    if ($this->permissionKeyExists($key)) {
                        $extras[] = $key;
                    }
                }
                if ($extras !== []) {
                    $this->permissions->grantRole($roleId, $extras);
                }
            }

            if ($template['slug'] === RoleSlug::MEMBER) {
                $memberId = $roleId;
            }
        }

        return $memberId;
    }

    /**
     * Align templates for every site that already has roles (install migration).
     */
    public function ensureForAllSites(): void
    {
        if (!$this->tableExists('sites') || !$this->tableExists('roles') || !$this->tableExists('permissions')) {
            return;
        }

        $sites = $this->pdo->query('SELECT id FROM sites');
        if ($sites === false) {
            return;
        }
        foreach ($sites->fetchAll(PDO::FETCH_COLUMN) as $siteId) {
            if (!is_string($siteId) || $siteId === '') {
                continue;
            }
            $this->ensureForSite(new SiteId($siteId));
        }
    }

    private function tableExists(string $name): bool
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name LIMIT 1",
            );
            $stmt->execute(['name' => $name]);

            return $stmt->fetchColumn() !== false;
        }

        // MySQL/MariaDB — table name cannot be bound; whitelist callers.
        $safe = preg_replace('/[^a-z0-9_]/', '', strtolower($name)) ?? '';
        if ($safe === '' || $safe !== strtolower($name)) {
            return false;
        }
        $table = $this->pdo->query("SHOW TABLES LIKE '{$safe}'");

        return $table !== false && $table->fetch() !== false;
    }

    private function ensureRole(SiteId $siteId, string $slug, string $name): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM roles
             WHERE slug = :slug AND site_id = :site_id
             LIMIT 1',
        );
        $stmt->execute(['slug' => $slug, 'site_id' => $siteId->value]);
        $id = $stmt->fetchColumn();
        if (is_string($id) && $id !== '') {
            $rename = $this->pdo->prepare(
                'UPDATE roles SET name = :name, updated_at = :updated_at
                 WHERE id = :id AND is_system = 1',
            );
            $rename->execute([
                'name' => $name,
                'updated_at' => gmdate('Y-m-d H:i:s.v'),
                'id' => $id,
            ]);

            return $id;
        }

        $id = Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $insert = $this->pdo->prepare(
            'INSERT INTO roles (id, site_id, name, slug, is_system, created_at, updated_at)
             VALUES (:id, :site_id, :name, :slug, 1, :created_at, :updated_at)',
        );
        $insert->execute([
            'id' => $id,
            'site_id' => $siteId->value,
            'name' => $name,
            'slug' => $slug,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    private function permissionKeyExists(string $key): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM permissions WHERE `key` = :key LIMIT 1');
        $stmt->execute(['key' => $key]);

        return $stmt->fetchColumn() !== false;
    }
}
