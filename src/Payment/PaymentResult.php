<?php

declare(strict_types=1);

namespace Nexis\Payment;

/**
 * Outcome of a PaymentPort operation (plugin / provider specific).
 */
final class PaymentResult
{
    public function __construct(
        public private(set) bool $ok,
        public private(set) string $providerRef = '',
        public private(set) string $message = '',
        /** @var array<string, mixed> */
        public private(set) array $meta = [],
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function success(string $providerRef = '', array $meta = []): self
    {
        return new self(true, $providerRef, '', $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function failure(string $message, array $meta = []): self
    {
        return new self(false, '', $message, $meta);
    }
}
