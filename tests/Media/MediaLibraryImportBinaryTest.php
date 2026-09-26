<?php

declare(strict_types=1);

namespace Nexis\Tests\Media;

use Nexis\Media\MediaAsset;
use Nexis\Media\MediaId;
use Nexis\Media\MediaLibrary;
use Nexis\Media\MediaRepository;
use Nexis\Site\SiteId;
use PHPUnit\Framework\TestCase;

final class MediaLibraryImportBinaryTest extends TestCase
{
    public function testImportBinaryPersistsWithGivenId(): void
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        self::assertNotFalse($png);

        $siteId = new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0601');
        $mediaId = new MediaId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0602');
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-media-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);

        $repo = $this->createMock(MediaRepository::class);
        $repo->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (MediaAsset $asset) use ($mediaId, $siteId): bool {
                return $asset->id->value === $mediaId->value
                    && $asset->siteId->value === $siteId->value
                    && $asset->mime === 'image/png'
                    && $asset->altText === 'Logo';
            }));

        try {
            $library = new MediaLibrary($repo, $dir);
            $asset = $library->importBinary($siteId, $mediaId, $png, 'logo.png', 'Logo');

            self::assertSame($mediaId->value, $asset->id->value);
            self::assertTrue(is_file($dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset->diskKey)));
        } finally {
            $this->rmTree($dir);
        }
    }

    public function testImportBinaryRejectsDisallowedType(): void
    {
        $library = new MediaLibrary(
            $this->createMock(MediaRepository::class),
            sys_get_temp_dir(),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('media.type');
        $library->importBinary(
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0603'),
            new MediaId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0604'),
            'not-an-image',
            'x.bin',
        );
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            $path = $file->getPathname();
            $file->isDir() ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
