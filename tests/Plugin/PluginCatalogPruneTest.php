<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugin\PluginCatalog;
use Nexis\Support\SystemClock;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PluginCatalogPruneTest extends TestCase
{
    public function testEmptyKnownKeysDoesNotWipeCatalog(): void
    {
        $pdo = $this->pdo();
        $this->seedPlugin($pdo, 'id-a', 'acme/a');
        $this->seedPlugin($pdo, 'id-b', 'acme/b');
        $this->seedInstall($pdo, 'inst-a', 'site-1', 'id-a');
        $this->seedInstall($pdo, 'inst-b', 'site-1', 'id-b');

        $catalog = new PluginCatalog($pdo, new SystemClock());
        $method = new ReflectionMethod(PluginCatalog::class, 'pruneMissing');
        $method->invoke($catalog, []);

        $pluginsCount = $pdo->query('SELECT COUNT(*) FROM plugins');
        $installsCount = $pdo->query('SELECT COUNT(*) FROM plugin_installations');
        self::assertNotFalse($pluginsCount);
        self::assertNotFalse($installsCount);
        self::assertSame(2, (int) $pluginsCount->fetchColumn());
        self::assertSame(2, (int) $installsCount->fetchColumn());
    }

    public function testPruneRemovesUnknownPluginsAndInstallations(): void
    {
        $pdo = $this->pdo();
        $this->seedPlugin($pdo, 'id-a', 'acme/a');
        $this->seedPlugin($pdo, 'id-b', 'acme/b');
        $this->seedInstall($pdo, 'inst-a', 'site-1', 'id-a');
        $this->seedInstall($pdo, 'inst-b', 'site-1', 'id-b');

        $catalog = new PluginCatalog($pdo, new SystemClock());
        $method = new ReflectionMethod(PluginCatalog::class, 'pruneMissing');
        $method->invoke($catalog, ['acme/a']);

        $keysStmt = $pdo->query('SELECT plugin_key FROM plugins ORDER BY plugin_key');
        self::assertNotFalse($keysStmt);
        $keys = $keysStmt->fetchAll(PDO::FETCH_COLUMN);
        self::assertSame(['acme/a'], $keys);
        $remaining = $pdo->query('SELECT COUNT(*) FROM plugin_installations');
        self::assertNotFalse($remaining);
        self::assertSame(1, (int) $remaining->fetchColumn());
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE plugins (
                id TEXT PRIMARY KEY,
                plugin_key TEXT UNIQUE,
                name TEXT,
                version TEXT,
                compatible_core TEXT,
                manifest TEXT,
                created_at TEXT,
                updated_at TEXT
            )',
        );
        $pdo->exec(
            'CREATE TABLE plugin_installations (
                id TEXT PRIMARY KEY,
                site_id TEXT,
                plugin_id TEXT,
                status TEXT,
                config TEXT,
                installed_at TEXT,
                updated_at TEXT,
                UNIQUE (site_id, plugin_id)
            )',
        );

        return $pdo;
    }

    private function seedPlugin(PDO $pdo, string $id, string $key): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO plugins (id, plugin_key, name, version, compatible_core, manifest, created_at, updated_at)
             VALUES (:id, :key, :name, :version, :core, :manifest, :ts, :ts)',
        );
        $stmt->execute([
            'id' => $id,
            'key' => $key,
            'name' => $key,
            'version' => '1.0.0',
            'core' => '^0.3',
            'manifest' => '{}',
            'ts' => '2026-01-01 00:00:00.000',
        ]);
    }

    private function seedInstall(PDO $pdo, string $id, string $siteId, string $pluginId): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO plugin_installations (id, site_id, plugin_id, status, config, installed_at, updated_at)
             VALUES (:id, :site, :plugin, :status, NULL, :ts, :ts)',
        );
        $stmt->execute([
            'id' => $id,
            'site' => $siteId,
            'plugin' => $pluginId,
            'status' => 'enabled',
            'ts' => '2026-01-01 00:00:00.000',
        ]);
    }
}
