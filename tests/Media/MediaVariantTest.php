<?php

declare(strict_types=1);

namespace Nexis\Tests\Media;

use Nexis\Media\MediaId;
use Nexis\Media\MediaVariant;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class MediaVariantTest extends TestCase
{
    public function testHumanSizeAndDimensions(): void
    {
        $variant = new MediaVariant(
            Uuid::v7(),
            new MediaId(Uuid::v7()),
            'thumb',
            'a/b.thumb.jpg',
            'image/jpeg',
            480,
            320,
            2048,
        );

        self::assertSame('2 KB', $variant->humanSize());
        self::assertSame('480×320', $variant->dimensionsLabel());

        $empty = new MediaVariant(
            Uuid::v7(),
            new MediaId(Uuid::v7()),
            'webp',
            'a/b.webp',
            'image/webp',
            null,
            null,
            512,
        );
        self::assertSame('512 B', $empty->humanSize());
        self::assertSame('', $empty->dimensionsLabel());
    }
}
