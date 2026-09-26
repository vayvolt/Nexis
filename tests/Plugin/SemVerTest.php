<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugin\SemVer;
use PHPUnit\Framework\TestCase;

final class SemVerTest extends TestCase
{
    public function testCaretConstraint(): void
    {
        self::assertTrue(SemVer::satisfies('0.1.0', '^0.1'));
        self::assertTrue(SemVer::satisfies('0.1.5', '^0.1'));
        self::assertFalse(SemVer::satisfies('0.2.0', '^0.1'));
        self::assertTrue(SemVer::satisfies('1.2.0', '^1.0'));
        self::assertFalse(SemVer::satisfies('2.0.0', '^1.0'));
    }

    public function testGreaterOrEqualConstraint(): void
    {
        self::assertTrue(SemVer::satisfies('8.4.18', '>=8.4'));
        self::assertTrue(SemVer::satisfies('8.4.0', '>=8.4'));
        self::assertFalse(SemVer::satisfies('8.3.0', '>=8.4'));
    }
}
