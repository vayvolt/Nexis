<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;

final class PluginCatalog
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    /**
     * Upsert discovered manifests into the catalog.
     *
     * @param list<PluginManifest> $manifests
     * @param bool $pruneMissing When true, remove catalog rows for plugins not in $manifests.
     *                           Must only be used with a full discovery list — never with a single
     *                           package install, or other installations are wiped.
     */
    public function sync(array $manifests, bool $pruneMissing = true): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $known = [];
        foreach ($manifests as $manifest) {
            $known[$manifest->id] = true;
            $manifestJson = json_encode($manifest->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $existing = $this->findPluginRow($manifest->id);
            if (
                $existing !== null
                && $existing['version'] === $manifest->version
                && $existing['compatible_core'] === $manifest->compatibleCore
                && $existing['name'] === $manifest->name
                && $existing['manifest'] === $manifestJson
            ) {
                continue;
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO plugins (id, plugin_key, name, version, compatible_core, manifest, created_at, updated_at)
                 VALUES (:id, :plugin_key, :name, :version, :compatible_core, :manifest, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    version = VALUES(version),
                    compatible_core = VALUES(compatible_core),
                    manifest = VALUES(manifest),
                    updated_at = VALUES(updated_at)',
            );
            $stmt->execute([
                'id' => $existing['id'] ?? Uuid::v7(),
                'plugin_key' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'compatible_core' => $manifest->compatibleCore,
                'manifest' => $manifestJson,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($pruneMissing) {
            $this->pruneMissing(array_keys($known));
        }
    }

    /**
     * Drop catalog rows for packages that no longer exist on disk.
     *
     * @param list<string> $knownKeys
     */
    private function pruneMissing(array $knownKeys): void
    {
        // Empty discovery must never wipe the catalog (transient FS/path failures).
        if ($knownKeys === []) {
            return;
        }
        $rows = $this->pdo->query('SELECT id, plugin_key FROM plugins');
        if ($rows === false) {
            return;
        }
        $known = array_fill_keys($knownKeys, true);
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = (string) ($row['plugin_key'] ?? '');
            if ($key === '' || isset($known[$key])) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $delInst = $this->pdo->prepare('DELETE FROM plugin_installations WHERE plugin_id = :id');
            $delInst->execute(['id' => $id]);
            $delPlugin = $this->pdo->prepare('DELETE FROM plugins WHERE id = :id');
            $delPlugin->execute(['id' => $id]);
        }
    }

    public function findPluginId(string $pluginKey): ?string
    {
        $row = $this->findPluginRow($pluginKey);

        return $row['id'] ?? null;
    }

    /**
     * @return array{id: string, name: string, version: string, compatible_core: string, manifest: string}|null
     */
    private function findPluginRow(string $pluginKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, version, compatible_core, manifest FROM plugins WHERE plugin_key = :key LIMIT 1',
        );
        $stmt->execute(['key' => $pluginKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['id'] ?? null) || $row['id'] === '') {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'version' => (string) ($row['version'] ?? ''),
            'compatible_core' => (string) ($row['compatible_core'] ?? ''),
            'manifest' => is_string($row['manifest'] ?? null)
                ? (string) $row['manifest']
                : (string) json_encode($row['manifest'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE),
        ];
    }

    public function ensureInstallation(SiteId $siteId, string $pluginKey, PluginInstallStatus $status = PluginInstallStatus::Installed): void
    {
        $pluginId = $this->findPluginId($pluginKey);
        if ($pluginId === null) {
            return;
        }
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_installations (id, site_id, plugin_id, status, config, installed_at, updated_at)
             VALUES (:id, :site_id, :plugin_id, :status, NULL, :installed_at, :updated_at)
             ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)',
        );
        $stmt->execute([
            'id' => Uuid::v7(),
            'site_id' => $siteId->value,
            'plugin_id' => $pluginId,
            'status' => $status->value,
            'installed_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setStatus(SiteId $siteId, string $pluginKey, PluginInstallStatus $status): bool
    {
        $pluginId = $this->findPluginId($pluginKey);
        if ($pluginId === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_installations
             SET status = :status, updated_at = :updated_at
             WHERE site_id = :site_id AND plugin_id = :plugin_id',
        );
        $stmt->execute([
            'status' => $status->value,
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
            'site_id' => $siteId->value,
            'plugin_id' => $pluginId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function removeInstallation(SiteId $siteId, string $pluginKey): bool
    {
        $pluginId = $this->findPluginId($pluginKey);
        if ($pluginId === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_installations WHERE site_id = :site_id AND plugin_id = :plugin_id',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'plugin_id' => $pluginId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Drop the plugin catalog row and every site installation for that package.
     */
    public function removePlugin(string $pluginKey): bool
    {
        $pluginId = $this->findPluginId($pluginKey);
        if ($pluginId === null) {
            return false;
        }
        $delInst = $this->pdo->prepare('DELETE FROM plugin_installations WHERE plugin_id = :id');
        $delInst->execute(['id' => $pluginId]);
        $delPlugin = $this->pdo->prepare('DELETE FROM plugins WHERE id = :id');
        $delPlugin->execute(['id' => $pluginId]);

        return $delPlugin->rowCount() > 0;
    }

    /**
     * @return list<string> plugin keys
     */
    public function enabledKeys(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.plugin_key
             FROM plugin_installations i
             INNER JOIN plugins p ON p.id = i.plugin_id
             WHERE i.site_id = :site_id AND i.status = :status
             ORDER BY p.plugin_key ASC',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'status' => PluginInstallStatus::Enabled->value,
        ]);
        $keys = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            if (is_string($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     version: string,
     *     author: string,
     *     status: string,
     *     license: string,
     *     licenseUri: string,
     *     compatibleCore: string,
     *     php: string,
     *     blocks: list<string>,
     *     permissions: list<string>,
     *     slots: list<string>
     * }>
     */
    public function listForSite(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.plugin_key AS `key`, p.name, p.version, p.compatible_core, p.manifest, COALESCE(i.status, :discovered) AS status
             FROM plugins p
             LEFT JOIN plugin_installations i ON i.plugin_id = p.id AND i.site_id = :site_id
             ORDER BY p.plugin_key ASC',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'discovered' => 'discovered',
        ]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $meta = $this->detailsFromManifest($row['manifest'] ?? null);
            $compatibleCore = trim((string) ($row['compatible_core'] ?? ''));
            if ($compatibleCore === '') {
                $compatibleCore = $meta['compatibleCore'];
            }
            $rows[] = [
                'key' => (string) $row['key'],
                'name' => (string) $row['name'],
                'version' => (string) $row['version'],
                'author' => $meta['author'],
                'status' => (string) $row['status'],
                'license' => $meta['license'],
                'licenseUri' => $meta['licenseUri'],
                'compatibleCore' => $compatibleCore,
                'php' => $meta['php'],
                'blocks' => $meta['blocks'],
                'permissions' => $meta['permissions'],
                'slots' => $meta['slots'],
            ];
        }

        return $rows;
    }

    /**
     * @return array{
     *     author: string,
     *     license: string,
     *     licenseUri: string,
     *     compatibleCore: string,
     *     php: string,
     *     blocks: list<string>,
     *     permissions: list<string>,
     *     slots: list<string>
     * }
     */
    private function detailsFromManifest(mixed $manifest): array
    {
        $empty = [
            'author' => '',
            'license' => '',
            'licenseUri' => '',
            'compatibleCore' => '',
            'php' => '',
            'blocks' => [],
            'permissions' => [],
            'slots' => [],
        ];
        $decoded = $this->decodeManifest($manifest);
        if ($decoded === null) {
            return $empty;
        }

        $author = '';
        if (isset($decoded['author']) && is_string($decoded['author'])) {
            $author = trim($decoded['author']);
        } elseif (isset($decoded['author']) && is_array($decoded['author'])) {
            $name = $decoded['author']['name'] ?? null;
            if (is_string($name)) {
                $author = trim($name);
            }
        }

        $provides = $decoded['provides'] ?? [];
        if (!is_array($provides)) {
            $provides = [];
        }

        return [
            'author' => $author,
            'license' => isset($decoded['license']) && is_string($decoded['license'])
                ? trim($decoded['license'])
                : '',
            'licenseUri' => isset($decoded['licenseUri']) && is_string($decoded['licenseUri'])
                ? trim($decoded['licenseUri'])
                : '',
            'compatibleCore' => isset($decoded['compatibleCore']) && is_string($decoded['compatibleCore'])
                ? trim($decoded['compatibleCore'])
                : '',
            'php' => isset($decoded['php']) && is_string($decoded['php'])
                ? trim($decoded['php'])
                : '',
            'blocks' => $this->stringList($provides['blocks'] ?? []),
            'permissions' => $this->stringList($provides['permissions'] ?? []),
            'slots' => $this->stringList($provides['slots'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeManifest(mixed $manifest): ?array
    {
        if (is_string($manifest) && $manifest !== '') {
            try {
                $decoded = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return null;
            }
        } elseif (is_array($manifest)) {
            $decoded = $manifest;
        } else {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}
