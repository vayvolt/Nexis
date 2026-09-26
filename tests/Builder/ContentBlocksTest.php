<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\Core\EmbedBlock;
use Nexis\Builder\Core\GalleryBlock;
use Nexis\Builder\Core\TableBlock;
use PHPUnit\Framework\TestCase;

final class ContentBlocksTest extends TestCase
{
    public function testGalleryParsesAssetIdsAndRendersGrid(): void
    {
        $html = (new GalleryBlock())->render([
            'type' => 'core/gallery',
            'props' => [
                'assetIds' => 'aaa, bbb;aaa',
                'columns' => '2',
                'caption' => 'Team',
            ],
        ], ['basePath' => '/nexis']);

        self::assertSame(['aaa', 'bbb'], GalleryBlock::parseAssetIds('aaa, bbb;aaa'));
        self::assertStringContainsString('bk-gallery--cols-2', $html);
        self::assertStringContainsString('/nexis/media/aaa', $html);
        self::assertStringContainsString('/nexis/media/bbb', $html);
        self::assertStringContainsString('Team', $html);
    }

    public function testGalleryUsesResolvedItemsWhenPresent(): void
    {
        $html = (new GalleryBlock())->render([
            'type' => 'core/gallery',
            'props' => [
                'assetIds' => 'ignored',
                'columns' => '3',
                'items' => [
                    ['src' => '/img/a.jpg', 'srcWebp' => '/img/a.webp', 'alt' => 'A'],
                ],
            ],
        ], []);

        self::assertStringContainsString('/img/a.jpg', $html);
        self::assertStringContainsString('type="image/webp"', $html);
        self::assertStringContainsString('alt="A"', $html);
        self::assertStringNotContainsString('/media/ignored', $html);
    }

    public function testEmbedConvertsYoutubeAndVimeo(): void
    {
        self::assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            EmbedBlock::toEmbedSrc('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
        );
        self::assertSame(
            'https://www.youtube-nocookie.com/embed/abcDEF12',
            EmbedBlock::toEmbedSrc('https://youtu.be/abcDEF12'),
        );
        self::assertSame(
            'https://player.vimeo.com/video/123456789',
            EmbedBlock::toEmbedSrc('https://vimeo.com/123456789'),
        );
        self::assertNull(EmbedBlock::toEmbedSrc('https://example.com/video'));

        $html = (new EmbedBlock())->render([
            'type' => 'core/embed',
            'props' => [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'title' => 'Demo',
                'aspect' => '16:9',
            ],
        ], []);
        self::assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        self::assertStringContainsString('bk-embed--16-9', $html);
        self::assertStringContainsString('title="Demo"', $html);
    }

    public function testTableParsesCsvWithHeader(): void
    {
        $rows = TableBlock::parseCsv("A,B\n1,2\n3,4");
        self::assertSame([['A', 'B'], ['1', '2'], ['3', '4']], $rows);

        $html = (new TableBlock())->render([
            'type' => 'core/table',
            'props' => [
                'csv' => "Name,Ort\nAda,Berlin",
                'header' => 'yes',
                'caption' => 'Personen',
            ],
        ], []);
        self::assertStringContainsString('<th scope="col">Name</th>', $html);
        self::assertStringContainsString('<td>Ada</td>', $html);
        self::assertStringContainsString('<caption>Personen</caption>', $html);
    }
}
