<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use DateTimeImmutable;
use Nexis\Auth\UserId;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Site\SiteId;
use PHPUnit\Framework\TestCase;

final class PageScheduleTest extends TestCase
{
    public function testIsScheduledFlag(): void
    {
        $page = new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0301'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0302'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0303',
            'de',
            'home',
            '/',
            'Geplant',
            null,
            PageStatus::Scheduled,
            scheduledAt: new DateTimeImmutable('2030-01-15 10:00:00'),
            scheduledBy: new UserId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0304'),
        );

        self::assertTrue($page->isScheduled);
        self::assertFalse($page->isPublished);
        self::assertSame('2030-01-15 10:00:00', $page->scheduledAt?->format('Y-m-d H:i:s'));
        self::assertSame('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0304', $page->scheduledBy?->value);
    }
}
