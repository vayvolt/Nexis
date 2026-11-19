<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure\Backup;

use Nexis\Infrastructure\Backup\BackupCatalog;
use Nexis\Infrastructure\Backup\LogicalBackup;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class BackupZipCatalogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-zip-backup-' . bin2hex(random_bytes(4));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testListsZipAndLegacyDirectory(): void
    {
        $stampZip = '20261112-100000';
        $stampDir = '20261112-090000';
        $this->writeZip($stampZip);
        $this->writeDir($stampDir);

        $catalog = new BackupCatalog(new LogicalBackup($this->root));
        self::assertSame([$stampZip, $stampDir], $catalog->stamps());

        $zipEntry = $catalog->find($stampZip);
        self::assertNotNull($zipEntry);
        self::assertTrue($zipEntry->isArchive);
        self::assertTrue($zipEntry->restorable());
        self::assertTrue($zipEntry->hasMedia);

        $dirEntry = $catalog->find($stampDir);
        self::assertNotNull($dirEntry);
        self::assertFalse($dirEntry->isArchive);
        self::assertTrue($dirEntry->restorable());
    }

    public function testExistsAndDeletePreferZip(): void
    {
        $stamp = '20261112-110000';
        $this->writeZip($stamp);
        $backup = new LogicalBackup($this->root);

        self::assertTrue($backup->exists($stamp));
        self::assertTrue($backup->delete($stamp));
        self::assertFalse($backup->exists($stamp));
        self::assertFileDoesNotExist((string) $backup->zipPathFor($stamp));
    }

    private function writeZip(string $stamp): void
    {
        $path = $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups'
            . DIRECTORY_SEPARATOR . $stamp . '.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('database.sql', "-- dump\nSELECT 1;\n");
        $zip->addFromString('manifest.json', json_encode([
            'database' => 'nexis',
            'database_ok' => true,
            'format' => 'zip',
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString('media/a.txt', 'x');
        $zip->close();
    }

    private function writeDir(string $stamp): void
    {
        $dir = $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups'
            . DIRECTORY_SEPARATOR . $stamp;
        mkdir($dir, 0775, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'database.sql', "-- dump\nSELECT 1;\n");
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', '{"database_ok":true}');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) && !is_file($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}
