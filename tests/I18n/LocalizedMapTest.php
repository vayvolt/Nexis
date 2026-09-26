<?php

declare(strict_types=1);

namespace Nexis\Tests\I18n;

use Nexis\I18n\LocalizedMap;
use PHPUnit\Framework\TestCase;

final class LocalizedMapTest extends TestCase
{
    public function testEncodeDecodeRoundTrip(): void
    {
        $json = LocalizedMap::encode(['de' => 'Hallo', 'en' => 'Hello']);
        self::assertSame(['de' => 'Hallo', 'en' => 'Hello'], LocalizedMap::decode($json));
    }

    public function testGetFallsBackToOtherLocaleThenFirstNonEmpty(): void
    {
        self::assertSame('Hello', LocalizedMap::get(['en' => 'Hello'], 'de', 'en'));
        self::assertSame('Hallo', LocalizedMap::get(['de' => 'Hallo', 'en' => ''], 'fr'));
        self::assertSame('', LocalizedMap::get([], 'de'));
    }

    public function testFromPostedTrimsEnabledLocalesOnly(): void
    {
        $map = LocalizedMap::fromPosted(
            ['de' => '  A  ', 'en' => 'B', 'fr' => 'ignored'],
            ['de', 'en'],
        );
        self::assertSame(['de' => 'A', 'en' => 'B'], $map);
    }
}
