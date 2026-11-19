<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Cron;

/**
 * Outcome of one cron invocation (CLI or web).
 */
final readonly class CronRunResult
{
    /**
     * @param list<string> $lines
     */
    private function __construct(
        public bool $ok,
        public bool $busy,
        public array $lines,
        public ?string $error,
    ) {
    }

    /**
     * @param list<string> $lines
     */
    public static function ok(array $lines): self
    {
        return new self(true, false, $lines, null);
    }

    public static function busy(): self
    {
        return new self(false, true, ['nexis cron already running or lock unavailable.'], null);
    }

    /**
     * @param list<string> $lines
     */
    public static function failed(string $error, array $lines = []): self
    {
        return new self(false, false, $lines, $error);
    }
}
