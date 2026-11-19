<?php

declare(strict_types=1);

namespace Nexis\Tests\Media;

use Nexis\Media\MediaLibrary;
use Nexis\Media\MediaRepository;
use Nexis\Site\SiteId;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;

final class MediaLibraryLimitsTest extends TestCase
{
    public function testRejectsOversizedPayload(): void
    {
        $library = new MediaLibrary(
            $this->createMock(MediaRepository::class),
            sys_get_temp_dir(),
            maxBytes: 8,
            maxPixels: 25_000_000,
        );

        $file = new UploadedFile(
            Stream::create('0123456789'),
            10,
            UPLOAD_ERR_OK,
            'x.pdf',
            'application/pdf',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('media.too_large');
        $library->store(new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0501'), $file, 'doc');
    }

    public function testRejectsTooManyPixels(): void
    {
        // 2x2 PNG
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEklEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        self::assertNotFalse($png);

        $library = new MediaLibrary(
            $this->createMock(MediaRepository::class),
            sys_get_temp_dir(),
            maxBytes: 1_000_000,
            maxPixels: 1,
        );

        $file = new UploadedFile(
            Stream::create($png),
            strlen($png),
            UPLOAD_ERR_OK,
            'tiny.png',
            'image/png',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('media.too_many_pixels');
        $library->store(new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0502'), $file, 'alt');
    }
}
