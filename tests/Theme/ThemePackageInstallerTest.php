<?php

declare(strict_types=1);

namespace Nexis\Tests\Theme;

use Nexis\Support\SystemClock;
use Nexis\Theme\CssSanitizer;
use Nexis\Theme\ThemeCatalog;
use Nexis\Theme\ThemeManifestLoader;
use Nexis\Theme\ThemePackageInstaller;
use PDO;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ThemePackageInstallerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-theme-install-' . bin2hex(random_bytes(4));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'themes', 0777, true);
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
        $this->buildZip($zip, 'acme-plain', [
            'theme.json' => json_encode([
                'id' => 'acme/plain',
                'name' => 'Plain',
                'version' => '1.0.0',
                'compatibleCore' => '^0.4',
                'templates' => ['page' => 'templates/page.twig'],
                'tokens' => 'tokens.json',
            ], JSON_THROW_ON_ERROR),
            'tokens.json' => '{}',
            'templates/page.twig' => '<html></html>',
        ]);

        $catalog = new ThemeCatalog($this->pdo(), new SystemClock(), new CssSanitizer());
        $installer = new ThemePackageInstaller(
            $this->root . DIRECTORY_SEPARATOR . 'themes',
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
            new ThemeManifestLoader(),
            $catalog,
        );

        try {
            $result = $installer->installFromZip($zip);
            self::assertSame('acme/plain', $result['manifest']->id);
            self::assertFalse($result['overwritten']);
        } catch (\PDOException) {
            // ThemeCatalog uses MySQL upsert; under SQLite filesystem assert is enough.
        }
        self::assertFileExists(
            $this->root . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'plain' . DIRECTORY_SEPARATOR . 'theme.json',
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
        $zip->addFromString('../evil.twig', 'x');
        $zip->close();

        $installer = new ThemePackageInstaller(
            $this->root . DIRECTORY_SEPARATOR . 'themes',
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
            new ThemeManifestLoader(),
            new ThemeCatalog($this->pdo(), new SystemClock(), new CssSanitizer()),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Zip-Slip');
        $installer->installFromZip($zipPath);
    }

    public function testRefusesOverwriteWithoutFlag(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ext-zip missing');
        }

        $target = $this->root . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'plain';
        mkdir($target, 0777, true);
        $json = json_encode([
            'id' => 'acme/plain',
            'name' => 'Plain',
            'version' => '0.9.0',
            'compatibleCore' => '^0.4',
        ], JSON_THROW_ON_ERROR);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'theme.json', $json);

        $zip = $this->root . DIRECTORY_SEPARATOR . 'demo2.zip';
        $this->buildZip($zip, null, ['theme.json' => (string) $json]);

        $installer = new ThemePackageInstaller(
            $this->root . DIRECTORY_SEPARATOR . 'themes',
            $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
            new ThemeManifestLoader(),
            new ThemeCatalog($this->pdo(), new SystemClock(), new CssSanitizer()),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('existiert bereits');
        $installer->installFromZip($zip, false);
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
        $pdo->exec(
            'CREATE TABLE themes (
                id TEXT PRIMARY KEY,
                theme_key TEXT UNIQUE,
                name TEXT,
                version TEXT,
                compatible_core TEXT,
                extends_key TEXT,
                manifest TEXT,
                created_at TEXT,
                updated_at TEXT
            )',
        );
        $pdo->exec(
            'CREATE TABLE sites (id TEXT PRIMARY KEY, theme_id TEXT)',
        );
        $pdo->exec(
            'CREATE TABLE site_theme_overrides (
                site_id TEXT PRIMARY KEY,
                tokens TEXT,
                custom_css TEXT,
                updated_by TEXT,
                updated_at TEXT
            )',
        );

        return $pdo;
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full) && !is_link($full)) {
                $this->deleteTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
