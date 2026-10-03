<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use DateTimeImmutable;

/**
 * Planer settings for automatic backups, stored as `backup.schedule.*` site settings.
 */
final readonly class BackupSchedule
{
    public const KEY_ENABLED = 'backup.schedule.enabled';
    public const KEY_INTERVAL = 'backup.schedule.interval';
    public const KEY_RETAIN = 'backup.schedule.retain';
    public const KEY_INCLUDE_MEDIA = 'backup.schedule.include_media';
    public const KEY_LAST_RUN = 'backup.schedule.last_run';

    public const INTERVAL_DAILY = 'daily';
    public const INTERVAL_WEEKLY = 'weekly';

    public const RETAIN_MIN = 1;
    public const RETAIN_MAX = 60;
    public const RETAIN_DEFAULT = 7;

    public function __construct(
        public bool $enabled = false,
        public string $interval = self::INTERVAL_DAILY,
        public int $retain = self::RETAIN_DEFAULT,
        public bool $includeMedia = true,
        public ?DateTimeImmutable $lastRunAt = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function intervals(): array
    {
        return [self::INTERVAL_DAILY, self::INTERVAL_WEEKLY];
    }

    public static function normalizeInterval(string $interval): string
    {
        return in_array($interval, self::intervals(), true) ? $interval : self::INTERVAL_DAILY;
    }

    public static function normalizeRetain(int $retain): int
    {
        return max(self::RETAIN_MIN, min(self::RETAIN_MAX, $retain));
    }

    public function intervalSeconds(): int
    {
        return $this->interval === self::INTERVAL_WEEKLY ? 7 * 86400 : 86400;
    }

    /**
     * Due when the schedule is on and the last run is at least one interval ago.
     * A schedule that never ran is due immediately.
     */
    public function isDue(DateTimeImmutable $now): bool
    {
        if (!$this->enabled) {
            return false;
        }
        if ($this->lastRunAt === null) {
            return true;
        }

        return $now->getTimestamp() - $this->lastRunAt->getTimestamp() >= $this->intervalSeconds();
    }

    public function withLastRunAt(?DateTimeImmutable $at): self
    {
        return new self($this->enabled, $this->interval, $this->retain, $this->includeMedia, $at);
    }

    /**
     * Stamps to remove so that at most `$retain` backups remain. Stamps are
     * sortable as strings (Ymd-His, UTC); entries that are not stamps are never
     * returned so foreign directories in storage/backups/ stay untouched.
     *
     * @param list<string> $stamps
     * @return list<string> oldest first
     */
    public static function prunable(array $stamps, int $retain): array
    {
        $valid = [];
        foreach ($stamps as $stamp) {
            if (LogicalBackup::isStamp($stamp)) {
                $valid[$stamp] = true;
            }
        }
        $sorted = array_keys($valid);
        rsort($sorted, SORT_STRING);

        $drop = array_slice($sorted, self::normalizeRetain($retain));

        return array_reverse(array_values($drop));
    }
}
