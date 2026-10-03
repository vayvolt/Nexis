<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure\Backup;

use DateTimeImmutable;
use DateTimeZone;
use Nexis\Infrastructure\Backup\BackupSchedule;
use PHPUnit\Framework\TestCase;

final class BackupScheduleTest extends TestCase
{
    public function testPrunesOldestBeyondRetention(): void
    {
        $stamps = ['20260101-010000', '20260104-010000', '20260102-010000', '20260103-010000'];

        self::assertSame(
            ['20260101-010000', '20260102-010000'],
            BackupSchedule::prunable($stamps, 2),
        );
    }

    public function testKeepsEverythingWhenRetentionIsLarger(): void
    {
        $stamps = ['20260101-010000', '20260102-010000'];

        self::assertSame([], BackupSchedule::prunable($stamps, 7));
    }

    public function testAlwaysKeepsAtLeastOneBackup(): void
    {
        $stamps = ['20260101-010000', '20260102-010000'];

        self::assertSame(['20260101-010000'], BackupSchedule::prunable($stamps, 0));
        self::assertSame(['20260101-010000'], BackupSchedule::prunable($stamps, -5));
    }

    public function testIgnoresDirectoriesThatAreNotStamps(): void
    {
        $stamps = ['20260101-010000', 'tmp', '.gitignore', '20260102-010000', '2026-01-03'];

        self::assertSame(['20260101-010000'], BackupSchedule::prunable($stamps, 1));
    }

    public function testDeduplicatesStamps(): void
    {
        $stamps = ['20260101-010000', '20260101-010000', '20260102-010000'];

        self::assertSame(['20260101-010000'], BackupSchedule::prunable($stamps, 1));
    }

    public function testDisabledScheduleIsNeverDue(): void
    {
        $schedule = new BackupSchedule(enabled: false);

        self::assertFalse($schedule->isDue($this->at('2026-01-01 00:00:00')));
    }

    public function testScheduleWithoutLastRunIsDue(): void
    {
        $schedule = new BackupSchedule(enabled: true);

        self::assertTrue($schedule->isDue($this->at('2026-01-01 00:00:00')));
    }

    public function testDailyScheduleIsDueAfterOneDay(): void
    {
        $schedule = new BackupSchedule(
            enabled: true,
            interval: BackupSchedule::INTERVAL_DAILY,
            lastRunAt: $this->at('2026-01-01 03:00:00'),
        );

        self::assertFalse($schedule->isDue($this->at('2026-01-02 02:59:59')));
        self::assertTrue($schedule->isDue($this->at('2026-01-02 03:00:00')));
    }

    public function testWeeklyScheduleIsDueAfterSevenDays(): void
    {
        $schedule = new BackupSchedule(
            enabled: true,
            interval: BackupSchedule::INTERVAL_WEEKLY,
            lastRunAt: $this->at('2026-01-01 03:00:00'),
        );

        self::assertFalse($schedule->isDue($this->at('2026-01-07 03:00:00')));
        self::assertTrue($schedule->isDue($this->at('2026-01-08 03:00:00')));
    }

    public function testNormalizesUnknownIntervalAndRetention(): void
    {
        self::assertSame(BackupSchedule::INTERVAL_DAILY, BackupSchedule::normalizeInterval('hourly'));
        self::assertSame(BackupSchedule::INTERVAL_WEEKLY, BackupSchedule::normalizeInterval('weekly'));
        self::assertSame(BackupSchedule::RETAIN_MIN, BackupSchedule::normalizeRetain(0));
        self::assertSame(BackupSchedule::RETAIN_MAX, BackupSchedule::normalizeRetain(999));
    }

    private function at(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }
}
