<?php

declare(strict_types=1);

namespace Nexis\Tests\Support;

use Nexis\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAddSameCurrency(): void
    {
        $a = Money::ofMinor(199, 'eur');
        $b = Money::ofMinor(1, 'EUR');
        $sum = $a->add($b);
        self::assertSame(200, $sum->amountMinor);
        self::assertSame('EUR', $sum->currency);
    }

    public function testRejectsCurrencyMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::ofMinor(100, 'EUR')->add(Money::ofMinor(50, 'USD'));
    }
}
