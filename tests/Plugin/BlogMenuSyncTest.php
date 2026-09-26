<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Content\PrimaryNavLinkSync;
use Nexis\Plugin\BlogMenuSync;
use PHPUnit\Framework\TestCase;

final class BlogMenuSyncTest extends TestCase
{
    public function testLooksLikeBlogUrl(): void
    {
        self::assertTrue(BlogMenuSync::looksLikeBlogUrl('/blog'));
        self::assertTrue(BlogMenuSync::looksLikeBlogUrl('/de/blog'));
        self::assertTrue(BlogMenuSync::looksLikeBlogUrl('/nexis/de/blog'));
        self::assertTrue(BlogMenuSync::looksLikeBlogUrl('/en/blog/'));
        self::assertFalse(BlogMenuSync::looksLikeBlogUrl('/de/blogger'));
        self::assertFalse(BlogMenuSync::looksLikeBlogUrl('/de/ueber-uns'));
        self::assertFalse(BlogMenuSync::looksLikeBlogUrl(''));
        self::assertTrue(PrimaryNavLinkSync::looksLikePathUrl('/de/blog', '/blog'));
    }
}
