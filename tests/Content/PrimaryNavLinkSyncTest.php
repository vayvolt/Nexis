<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\PrimaryNavLinkSync;
use PHPUnit\Framework\TestCase;

final class PrimaryNavLinkSyncTest extends TestCase
{
    public function testLooksLikePathUrl(): void
    {
        self::assertTrue(PrimaryNavLinkSync::looksLikePathUrl('/blog', '/blog'));
        self::assertTrue(PrimaryNavLinkSync::looksLikePathUrl('/de/blog', '/blog'));
        self::assertTrue(PrimaryNavLinkSync::looksLikePathUrl('/nexis/de/blog', '/blog'));
        self::assertFalse(PrimaryNavLinkSync::looksLikePathUrl('/de/blogger', '/blog'));
        self::assertTrue(PrimaryNavLinkSync::looksLikePathUrl('/en/catalog/', '/catalog'));
        self::assertFalse(PrimaryNavLinkSync::looksLikePathUrl('/de/catalogue', '/catalog'));
    }
}
