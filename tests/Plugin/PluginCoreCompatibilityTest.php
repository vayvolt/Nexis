<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugin\PluginCoreCompatibility;
use PHPUnit\Framework\TestCase;

final class PluginCoreCompatibilityTest extends TestCase
{
    public function testMatchesCaretConstraint(): void
    {
        self::assertTrue(PluginCoreCompatibility::isCompatibleWithCore('^0.3', '0.3.0'));
        self::assertTrue(PluginCoreCompatibility::isCompatibleWithCore('^0.3', '0.3.9'));
        self::assertFalse(PluginCoreCompatibility::isCompatibleWithCore('^0.3', '0.4.0'));
    }

    public function testRejectsEmptyConstraint(): void
    {
        self::assertFalse(PluginCoreCompatibility::isCompatibleWithCore(''));
    }
}
