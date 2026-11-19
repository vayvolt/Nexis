<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

/**
 * Outcome of one BackupScheduleRunner pass — also used for CLI output.
 */
final readonly class BackupRunResult
{
    public const REASON_NO_SITE = 'no_site';
    public const REASON_DISABLED = 'disabled';
    public const REASON_NOT_DUE = 'not_due';
    public const REASON_LOCKED = 'locked';
    public const REASON_CREATED = 'created';
    public const REASON_FAILED = 'failed';

    /**
     * @param list<string> $pruned
     */
    public function __construct(
        public string $reason,
        public ?string $stamp = null,
        public array $pruned = [],
        public ?string $error = null,
    ) {
    }

    public function created(): bool
    {
        return $this->reason === self::REASON_CREATED;
    }

    public function failed(): bool
    {
        return $this->reason === self::REASON_FAILED;
    }
}
