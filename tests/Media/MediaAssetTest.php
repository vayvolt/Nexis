<?php

declare(strict_types=1);

namespace Nexis\Tests\Media;

use Nexis\Media\MediaAsset;
use Nexis\Media\MediaId;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class MediaAssetTest extends TestCase
{
    public function testHumanSizeAndImageFlag(): void
    {
        $image = new MediaAsset(
            new MediaId(Uuid::v7()),
            new SiteId(Uuid::v7()),
            'a/b.png',
            'logo.png',
            'Firmenlogo',
            'image/png',
            'png',
            2048,
            100,
            40,
            str_repeat('a', 64),
        );

        self::assertTrue($image->isImage());
        self::assertSame('2 KB', $image->humanSize());
        self::assertSame('Firmenlogo', $image->displayAlt());
        self::assertSame('50% 50%', $image->objectPositionCss());
        self::assertSame(0.0, MediaAsset::clampFocus(-5));
        self::assertSame(100.0, MediaAsset::clampFocus(120));

        $focused = new MediaAsset(
            new MediaId(Uuid::v7()),
            new SiteId(Uuid::v7()),
            'a/c.png',
            'hero.png',
            'Hero',
            'image/png',
            'png',
            1024,
            200,
            100,
            str_repeat('c', 64),
            null,
            null,
            20.5,
            80,
        );
        self::assertSame('20.5% 80%', $focused->objectPositionCss());

        $pdf = new MediaAsset(
            new MediaId(Uuid::v7()),
            new SiteId(Uuid::v7()),
            'a/b.pdf',
            'doc.pdf',
            null,
            'application/pdf',
            'pdf',
            512,
            null,
            null,
            str_repeat('b', 64),
        );
        self::assertFalse($pdf->isImage());
        self::assertSame('512 B', $pdf->humanSize());
        self::assertSame('doc.pdf', $pdf->displayAlt());
    }
}
