<?php

declare(strict_types=1);

namespace Nexis\Support;

use InvalidArgumentException;

/**
 * Immutable monetary amount in minor units (e.g. cents) with ISO 4217 currency.
 */
final class Money
{
    public function __construct(
        public private(set) int $amountMinor,
        public private(set) string $currency,
    ) {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money amount must be >= 0.');
        }
        $currency = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO 4217 code.');
        }
        $this->currency = $currency;
    }

    public static function ofMinor(int $amountMinor, string $currency): self
    {
        return new self($amountMinor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->amountMinor - $other->amountMinor;
        if ($result < 0) {
            throw new InvalidArgumentException('Money subtract would be negative.');
        }

        return new self($result, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                'Currency mismatch: ' . $this->currency . ' vs ' . $other->currency,
            );
        }
    }
}
