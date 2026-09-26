<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Site\SiteId;
use PHPUnit\Framework\TestCase;

final class PageSeoTest extends TestCase
{
    public function testDocumentTitleFallsBackToTitle(): void
    {
        $page = new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0011'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0012'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0013',
            'de',
            'home',
            '/',
            'Willkommen',
            null,
            PageStatus::Draft,
        );
        self::assertSame('Willkommen', $page->documentTitle);
    }

    public function testDocumentTitlePrefersMetaTitle(): void
    {
        $page = new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0021'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0022'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0023',
            'de',
            'home',
            '/',
            'Willkommen',
            null,
            PageStatus::Draft,
            metaTitle: 'SEO Titel',
            metaDescription: 'Beschreibung',
        );
        self::assertSame('SEO Titel', $page->documentTitle);
        self::assertSame('Beschreibung', $page->metaDescription);
        self::assertSame('index,follow', $page->robots);
    }
}
