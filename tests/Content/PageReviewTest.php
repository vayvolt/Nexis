<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Auth\Permission;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Site\SiteId;
use PHPUnit\Framework\TestCase;

final class PageReviewTest extends TestCase
{
    public function testInReviewFlag(): void
    {
        $page = new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0401'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0402'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0403',
            'de',
            'home',
            '/',
            'In Prüfung',
            null,
            PageStatus::InReview,
        );

        self::assertTrue($page->isInReview);
        self::assertFalse($page->isPublished);
        self::assertFalse($page->isScheduled);
        self::assertSame('in_review', $page->status->value);
    }

    public function testEditorDefaultsExcludePublish(): void
    {
        $defaults = Permission::editorDefaults();
        self::assertContains(Permission::CONTENT_PAGE_SUBMIT_REVIEW, $defaults);
        self::assertContains(Permission::CONTENT_PAGE_SEO, $defaults);
        self::assertNotContains(Permission::CONTENT_PAGE_PUBLISH, $defaults);
        self::assertNotContains(Permission::THEME_MANAGE, $defaults);
        self::assertContains(Permission::CONTENT_PAGE_PUBLISH, Permission::core());
    }

    public function testSeoDefaultsAreMetaOnly(): void
    {
        $defaults = Permission::seoDefaults();
        self::assertContains(Permission::CONTENT_PAGE_SEO, $defaults);
        self::assertContains(Permission::CONTENT_MEDIA_MANAGE, $defaults);
        self::assertNotContains(Permission::CONTENT_PAGE_EDIT, $defaults);
        self::assertNotContains(Permission::CONTENT_PAGE_PUBLISH, $defaults);
    }
}