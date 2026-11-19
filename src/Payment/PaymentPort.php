<?php

declare(strict_types=1);

namespace Nexis\Payment;

use Nexis\Support\Money;

/**
 * Optional payment gateway port for shop / checkout plugins.
 * Core ships no default implementation; plugins bind their own.
 *
 * @param array<string, mixed> $context Order id, customer, return URLs, etc.
 */
interface PaymentPort
{
    /**
     * @param array<string, mixed> $context
     */
    public function charge(Money $amount, array $context = []): PaymentResult;

    /**
     * @param array<string, mixed> $context
     */
    public function refund(Money $amount, string $providerRef, array $context = []): PaymentResult;
}
