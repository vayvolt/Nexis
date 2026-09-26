<?php

declare(strict_types=1);

namespace Nexis\Tests\Kernel;

use Nexis\Kernel\Nexis;
use PHPUnit\Framework\TestCase;

final class NexisVersionTest extends TestCase
{
    public function testProductIdentityIsDefined(): void
    {
        self::assertSame('Nexis', Nexis::NAME);
        self::assertSame('0.3.0', Nexis::VERSION);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Nexis::VERSION);
        self::assertNotSame('', Nexis::TAGLINE);
        self::assertSame('Vayvolt', Nexis::VENDOR);
        self::assertStringContainsString('Vayvolt', Nexis::ATTRIBUTION);
        self::assertNotSame('', Nexis::VENDOR_URL);
        self::assertSame('GPL-2.0-or-later', Nexis::LICENSE);
        self::assertStringContainsString('2026', Nexis::copyrightNotice());
        self::assertFileExists(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'LICENSE');
        self::assertFileExists(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR . 'nexis-icon.svg');
        self::assertFileExists(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'brand' . DIRECTORY_SEPARATOR . 'nexis-logo.svg');
        self::assertSame('/assets/brand/nexis-logo.svg', Nexis::brandUrl(''));
        self::assertSame('/nexis/assets/brand/nexis-icon.svg', Nexis::brandUrl('/nexis', Nexis::BRAND_ICON));
    }
}
