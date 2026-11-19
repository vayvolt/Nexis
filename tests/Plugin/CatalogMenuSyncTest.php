<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugin\CatalogMenuSync;
use PHPUnit\Framework\TestCase;

final class CatalogMenuSyncTest extends TestCase
{
    public function testLooksLikeCatalogUrl(): void
    {
        self::assertTrue(CatalogMenuSync::looksLikeCatalogUrl('/catalog'));
        self::assertTrue(CatalogMenuSync::looksLikeCatalogUrl('/de/catalog'));
        self::assertTrue(CatalogMenuSync::looksLikeCatalogUrl('/nexis/de/catalog'));
        self::assertTrue(CatalogMenuSync::looksLikeCatalogUrl('/en/catalog/'));
        self::assertFalse(CatalogMenuSync::looksLikeCatalogUrl('/de/catalogue'));
        self::assertFalse(CatalogMenuSync::looksLikeCatalogUrl('/de/ueber-uns'));
        self::assertFalse(CatalogMenuSync::looksLikeCatalogUrl(''));
    }
}
