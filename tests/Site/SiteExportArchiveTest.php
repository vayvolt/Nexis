<?php

declare(strict_types=1);

namespace Nexis\Tests\Site;

use Nexis\Site\SiteExportArchive;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class SiteExportArchiveTest extends TestCase
{
    public function testReadsValidArchive(): void
    {
        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-ok-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('site.json', json_encode([
            'format' => 'nexis.site.export',
            'version' => 1,
            'pages' => [],
            'media' => [],
        ], JSON_THROW_ON_ERROR));
        $zip->close();

        try {
            $payload = SiteExportArchive::read($zipPath);
            self::assertSame('nexis.site.export', $payload['format']);
        } finally {
            @unlink($zipPath);
        }
    }

    public function testRejectsUnknownFormat(): void
    {
        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-bad-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('site.json', json_encode(['format' => 'other'], JSON_THROW_ON_ERROR));
        $zip->close();

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unbekanntes Export-Format');
            SiteExportArchive::read($zipPath);
        } finally {
            @unlink($zipPath);
        }
    }
}
