<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Auth\PdoPermissionLookup;
use Nexis\Event\EventDispatcher;
use Nexis\Kernel\Config;
use Nexis\Plugin\ManifestLoader;
use Nexis\Plugin\PluginAssetPublisher;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginDiscovery;
use Nexis\Plugin\PluginInstallStatus;
use Nexis\Plugin\PluginUninstaller;
use Nexis\Plugin\SettingsSchemaRegistry;
use Nexis\Security\SecretBox;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class PluginUninstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = bin2hex(random_bytes(32));
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-plugin-uninstall-' . bin2hex(random_bytes(4));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo', 0777, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo', 0777, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'site-1', 0777, true);
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
        $this->deleteTree($this->root);
    }

    public function testUninstallRemovesPackageAssetsStorageAndCatalog(): void
    {
        $pluginDir = $this->root . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo';
        file_put_contents($pluginDir . DIRECTORY_SEPARATOR . 'plugin.json', json_encode([
            'id' => 'acme/demo',
            'name' => 'Demo',
            'version' => '1.0.0',
            'compatibleCore' => '^0.3',
            'autoload' => 'Acme\\Demo\\',
            'provider' => 'Acme\\Demo\\DemoServiceProvider',
        ], JSON_THROW_ON_ERROR));
        file_put_contents(
            $this->root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'x.css',
            'body{}',
        );
        file_put_contents(
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo' . DIRECTORY_SEPARATOR . 'site-1' . DIRECTORY_SEPARATOR . 'keep.txt',
            'x',
        );

        $pdo = $this->pdo();
        $pluginId = Uuid::v7();
        $pdo->prepare(
            'INSERT INTO plugins (id, plugin_key, name, version, compatible_core, manifest, created_at, updated_at)
             VALUES (:id, :key, :name, :version, :core, :manifest, :ts, :ts)',
        )->execute([
            'id' => $pluginId,
            'key' => 'acme/demo',
            'name' => 'Demo',
            'version' => '1.0.0',
            'core' => '^0.3',
            'manifest' => '{}',
            'ts' => '2026-01-01 00:00:00.000',
        ]);
        $siteId = new SiteId(Uuid::v7());
        $pdo->prepare(
            'INSERT INTO plugin_installations (id, site_id, plugin_id, status, config, installed_at, updated_at)
             VALUES (:id, :site, :plugin, :status, NULL, :ts, :ts)',
        )->execute([
            'id' => Uuid::v7(),
            'site' => $siteId->value,
            'plugin' => $pluginId,
            'status' => PluginInstallStatus::Enabled->value,
            'ts' => '2026-01-01 00:00:00.000',
        ]);

        $catalog = new PluginCatalog($pdo, new SystemClock());
        $discovery = new PluginDiscovery(
            $this->root . DIRECTORY_SEPARATOR . 'plugins',
            new ManifestLoader(),
        );
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException('unexpected');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };

        $uninstaller = new PluginUninstaller(
            $discovery,
            $catalog,
            new EventDispatcher(),
            $container,
            new NullLogger(),
            $this->root,
            new PdoPermissionLookup($pdo),
            new PdoSiteSettingsRepository($pdo, new SecretBox(Config::load(sys_get_temp_dir()))),
            new SettingsSchemaRegistry(),
            new PluginAssetPublisher($this->root),
        );

        $uninstaller->uninstall($siteId, 'acme/demo');

        self::assertDirectoryDoesNotExist($pluginDir);
        self::assertDirectoryDoesNotExist(
            $this->root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo',
        );
        self::assertDirectoryDoesNotExist(
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo',
        );
        self::assertNull($catalog->findPluginId('acme/demo'));
        self::assertSame([], $catalog->enabledKeys($siteId));
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
        $pdo->exec(
            'CREATE TABLE site_settings (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                `key` TEXT NOT NULL,
                value TEXT NOT NULL,
                encrypted INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )',
        );
        $pdo->exec('CREATE TABLE roles (id TEXT PRIMARY KEY, site_id TEXT)');
        $pdo->exec('CREATE TABLE permissions (id TEXT PRIMARY KEY, `key` TEXT)');
        $pdo->exec('CREATE TABLE role_permissions (role_id TEXT, permission_id TEXT)');

        return $pdo;
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
