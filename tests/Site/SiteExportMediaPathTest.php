<?php

declare(strict_types=1);

namespace Nexis\Tests\Site;

use Nexis\Site\SiteExportArchive;
use Nexis\Site\SiteExporter;
use Nexis\Site\SiteImportResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class SiteExportMediaPathTest extends TestCase
{
    #[Test]
    public function media_zip_entry_uses_full_disk_key(): void
    {
        self::assertSame(
            'media/site-id/2026/11/asset.bin',
            SiteExporter::mediaZipEntry('site-id/2026/11/asset.bin'),
        );
        self::assertNull(SiteExporter::mediaZipEntry('../evil'));
        self::assertNull(SiteExporter::mediaZipEntry('/absolute'));
        self::assertNull(SiteExporter::mediaZipEntry(''));
    }

    #[Test]
    public function media_binary_prefers_v3_then_falls_back_to_v2_basename(): void
    {
        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-media-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('media/legacy-name.png', 'V2BYTES');
        $zip->addFromString('media/site/2026/11/asset.png', 'V3BYTES');
        $zip->close();

        try {
            $zip = new ZipArchive();
            self::assertTrue($zip->open($zipPath));
            self::assertSame('V3BYTES', SiteExportArchive::mediaBinary($zip, 'site/2026/11/asset.png'));
            self::assertSame('V2BYTES', SiteExportArchive::mediaBinary($zip, 'other/path/legacy-name.png'));
            self::assertNull(SiteExportArchive::mediaBinary($zip, 'missing/file.png'));
            $zip->close();
        } finally {
            @unlink($zipPath);
        }
    }

    #[Test]
    public function import_result_to_array_includes_dry_run_and_warnings(): void
    {
        $result = new SiteImportResult(
            dryRun: true,
            pages: 2,
            pagesUpdated: 1,
            media: 3,
            mediaUpdated: 1,
            mediaSkipped: 1,
            published: 1,
            menus: 1,
            redirects: 0,
            settings: 4,
            warnings: ['note'],
        );
        $arr = $result->toArray();
        self::assertTrue($arr['dry_run']);
        self::assertSame(1, $arr['pages_updated']);
        self::assertSame(1, $arr['media_skipped']);
        self::assertSame(['note'], $arr['warnings']);
    }
}
