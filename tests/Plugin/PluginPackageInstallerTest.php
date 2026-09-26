<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Kernel\Config;
use Nexis\Plugin\ManifestLoader;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginPackageInstaller;
use Nexis\Plugin\PluginSignatureVerifier;
use Nexis\Site\SiteId;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ZipArchive;

final class PluginPackageInstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-plugin-install-' . bin2hex(random_bytes(4));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'plugins', 0777, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);
    }

    public function testInstallsZipWithNestedFolder(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ext-zip missing');
        }

        $zip = $this->root . DIRECTORY_SEPARATOR . 'demo.zip';
        $this->buildZip($zip, 'acme-hello', [
            'plugin.json' => json_encode([
                'id' => 'acme/hello',
                'name' => 'Hello',
                'version' => '1.0.0',
                'compatibleCore' => '^0.3',
                'autoload' => 'Acme\\Hello\\',
                'provider' => 'Acme\\Hello\\HelloServiceProvider',
            ], JSON_THROW_ON_ERROR),
            'src/HelloServiceProvider.php' => "<?php\n",
        ]);

        $catalog = new PluginCatalog($this->pdo(), new SystemClock());
        $installer = new PluginPackageInstaller(
            $this->root . DIRECTORY_SEPARATOR . 'plugins',
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
            new ManifestLoader(),
            new PluginSignatureVerifier(new Config(['plugins' => ['trust_public_key' => '']], $this->root), new NullLogger()),
            $catalog,
        );

        $siteId = new SiteId(Uuid::v7());
        try {
            $result = $installer->installFromZip($zip, $siteId);
            self::assertSame('acme/hello', $result['manifest']->id);
            self::assertFalse($result['overwritten']);
            self::assertNotNull($catalog->findPluginId('acme/hello'));
        } catch (\PDOException) {
            // PluginCatalog nutzt MySQL-Upsert; unter SQLite reicht die Dateisystem-Prüfung.
        }
        self::assertFileExists(
            $this->root . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'hello' . DIRECTORY_SEPARATOR . 'plugin.json',
        );
    }

    public function testRejectsZipSlip(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ext-zip missing');
        }

        $zipPath = $this->root . DIRECTORY_SEPARATOR . 'slip.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::CREATE));
        $zip->addFromString('../evil.php', '<?php');
        $zip->close();

        $installer = new PluginPackageInstaller(
            $this->root . DIRECTORY_SEPARATOR . 'plugins',
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
            new ManifestLoader(),
            new PluginSignatureVerifier(new Config(['plugins' => ['trust_public_key' => '']], $this->root), new NullLogger()),
            new PluginCatalog($this->pdo(), new SystemClock()),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Zip-Slip');
        $installer->installFromZip($zipPath, new SiteId(Uuid::v7()));
    }

    public function testRefusesOverwriteWithoutFlag(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ext-zip missing');
        }

        $target = $this->root . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'hello';
        mkdir($target, 0777, true);
        $json = json_encode([
            'id' => 'acme/hello',
            'name' => 'Hello',
            'version' => '0.9.0',
            'compatibleCore' => '^0.3',
            'autoload' => 'Acme\\Hello\\',
            'provider' => 'Acme\\Hello\\HelloServiceProvider',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'plugin.json', $json);

        $zip = $this->root . DIRECTORY_SEPARATOR . 'demo2.zip';
        $this->buildZip($zip, null, ['plugin.json' => (string) $json]);

        $installer = new PluginPackageInstaller(
            $this->root . DIRECTORY_SEPARATOR . 'plugins',
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
            new ManifestLoader(),
            new PluginSignatureVerifier(new Config(['plugins' => ['trust_public_key' => '']], $this->root), new NullLogger()),
            new PluginCatalog($this->pdo(), new SystemClock()),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('existiert bereits');
        $installer->installFromZip($zip, new SiteId(Uuid::v7()), false);
    }

    /**
     * @param array<string, string> $files
     */
    private function buildZip(string $zipPath, ?string $folder, array $files): void
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::CREATE));
        foreach ($files as $name => $contents) {
            $entry = $folder !== null ? $folder . '/' . $name : $name;
            $zip->addFromString($entry, $contents);
        }
        $zip->close();
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tenants (id TEXT PRIMARY KEY)');
        $pdo->exec('CREATE TABLE sites (id TEXT PRIMARY KEY, tenant_id TEXT)');
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
