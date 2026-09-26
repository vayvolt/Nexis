<?php

declare(strict_types=1);

namespace Nexis\Theme;

use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;

final class ThemeCatalog
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
        private CssSanitizer $css,
    ) {
    }

    /**
     * @param list<ThemeManifest> $manifests
     */
    public function sync(array $manifests): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        foreach ($manifests as $manifest) {
            $manifestJson = json_encode($manifest->raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $existing = $this->findThemeRow($manifest->id);
            if (
                $existing !== null
                && $existing['version'] === $manifest->version
                && $existing['compatible_core'] === $manifest->compatibleCore
                && $existing['name'] === $manifest->name
                && ($existing['extends_key'] ?? null) === $manifest->extends
                && $existing['manifest'] === $manifestJson
            ) {
                continue;
            }
            $stmt = $this->pdo->prepare(
                'INSERT INTO themes (id, theme_key, name, version, compatible_core, extends_key, manifest, created_at, updated_at)
                 VALUES (:id, :theme_key, :name, :version, :compatible_core, :extends_key, :manifest, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    version = VALUES(version),
                    compatible_core = VALUES(compatible_core),
                    extends_key = VALUES(extends_key),
                    manifest = VALUES(manifest),
                    updated_at = VALUES(updated_at)',
            );
            $stmt->execute([
                'id' => $existing['id'] ?? Uuid::v7(),
                'theme_key' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'compatible_core' => $manifest->compatibleCore,
                'extends_key' => $manifest->extends,
                'manifest' => $manifestJson,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function findId(string $themeKey): ?string
    {
        $row = $this->findThemeRow($themeKey);

        return $row['id'] ?? null;
    }

    /**
     * @return array{id: string, name: string, version: string, compatible_core: string, extends_key: ?string, manifest: string}|null
     */
    private function findThemeRow(string $themeKey): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, version, compatible_core, extends_key, manifest FROM themes WHERE theme_key = :key LIMIT 1',
        );
        $stmt->execute(['key' => $themeKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !is_string($row['id'] ?? null) || $row['id'] === '') {
            return null;
        }
        $extends = $row['extends_key'] ?? null;

        return [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'version' => (string) ($row['version'] ?? ''),
            'compatible_core' => (string) ($row['compatible_core'] ?? ''),
            'extends_key' => is_string($extends) && $extends !== '' ? $extends : null,
            'manifest' => is_string($row['manifest'] ?? null)
                ? (string) $row['manifest']
                : (string) json_encode($row['manifest'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE),
        ];
    }

    public function themeKeyForSite(SiteId $siteId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.theme_key
             FROM sites s
             LEFT JOIN themes t ON t.id = s.theme_id
             WHERE s.id = :id
             LIMIT 1',
        );
        $stmt->execute(['id' => $siteId->value]);
        $key = $stmt->fetchColumn();

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function assignTheme(SiteId $siteId, string $themeKey): bool
    {
        $themeId = $this->findId($themeKey);
        if ($themeId === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE sites SET theme_id = :theme_id, updated_at = :updated_at WHERE id = :id',
        );
        $stmt->execute([
            'theme_id' => $themeId,
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
            'id' => $siteId->value,
        ]);

        return $stmt->rowCount() >= 0;
    }

    /**
     * @return array<string, string>
     */
    public function overrides(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare('SELECT tokens, custom_css FROM site_theme_overrides WHERE site_id = :site_id LIMIT 1');
        $stmt->execute(['site_id' => $siteId->value]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return [];
        }
        $tokens = $row['tokens'];
        if (is_string($tokens)) {
            $decoded = json_decode($tokens, true);
        } else {
            $decoded = $tokens;
        }
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $out[$key] = $value;
            }
        }

        return $this->css->sanitizeTokenMap($out);
    }

    public function customCss(SiteId $siteId): string
    {
        $stmt = $this->pdo->prepare('SELECT custom_css FROM site_theme_overrides WHERE site_id = :site_id LIMIT 1');
        $stmt->execute(['site_id' => $siteId->value]);
        $css = $stmt->fetchColumn();

        return is_string($css) ? $this->css->sanitizeStylesheet($css) : '';
    }

    /**
     * @param array<string, string> $tokens
     */
    public function saveOverrides(SiteId $siteId, array $tokens, ?string $customCss, ?string $userId): void
    {
        $tokens = $this->css->sanitizeTokenMap($tokens);
        if ($customCss !== null) {
            $customCss = $this->css->sanitizeStylesheet($customCss);
            if ($customCss === '') {
                $customCss = null;
            }
        }
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO site_theme_overrides (site_id, tokens, custom_css, updated_by, updated_at)
             VALUES (:site_id, :tokens, :custom_css, :updated_by, :updated_at)
             ON DUPLICATE KEY UPDATE
                tokens = VALUES(tokens),
                custom_css = VALUES(custom_css),
                updated_by = VALUES(updated_by),
                updated_at = VALUES(updated_at)',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'tokens' => json_encode($tokens, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'custom_css' => $customCss,
            'updated_by' => $userId,
            'updated_at' => $now,
        ]);
    }
}
