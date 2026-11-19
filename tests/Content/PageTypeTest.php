<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\Site\SiteId;
use PHPUnit\Framework\TestCase;

final class PageTypeTest extends TestCase
{
    public function testDefaultTypeIsPage(): void
    {
        $page = new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0401'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0402'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0403',
            'de',
            'home',
            '/',
            'Start',
            null,
            PageStatus::Draft,
        );
        self::assertSame(PageType::PAGE, $page->type);
        self::assertFalse($page->isPost);
    }

    public function testPostTypeFlag(): void
    {
        $page = new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0411'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0412'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0413',
            'de',
            'hello',
            '/blog/hello',
            'Hello',
            null,
            PageStatus::Published,
            type: PageType::POST,
        );
        self::assertTrue($page->isPost);
        self::assertFalse($page->isProduct);
        self::assertSame(PageType::POST, $page->type);
    }

    public function testProductTypeFlag(): void
    {
        $page = new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0421'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0422'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0423',
            'de',
            'lampe',
            '/catalog/lampe',
            'Lampe',
            null,
            PageStatus::Published,
            type: PageType::PRODUCT,
        );
        self::assertTrue($page->isProduct);
        self::assertFalse($page->isPost);
        self::assertSame(PageType::PRODUCT, $page->type);
    }
}
