<?php

declare(strict_types=1);

namespace Nexis\Install;

/**
 * Outcome of installer database connectivity / privilege checks.
 */
final class DatabasePreflightResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $code,
        public readonly string $detail,
    ) {
    }

    public static function success(): self
    {
        return new self(true, 'ok', '');
    }

    public static function failure(string $code, string $detail = ''): self
    {
        return new self(false, $code, $detail);
    }
}
