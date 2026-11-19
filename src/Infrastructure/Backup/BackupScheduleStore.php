<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use DateTimeImmutable;
use DateTimeZone;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;

/**
 * Persists BackupSchedule in site_settings (`backup.schedule.*`).
 */
final class BackupScheduleStore
{
    public function __construct(
        private PdoSiteSettingsRepository $settings,
    ) {
    }

    public function load(SiteId $siteId): BackupSchedule
    {
        $retain = $this->settings->get($siteId, BackupSchedule::KEY_RETAIN, BackupSchedule::RETAIN_DEFAULT);
        $interval = $this->settings->get($siteId, BackupSchedule::KEY_INTERVAL, BackupSchedule::INTERVAL_DAILY);

        return new BackupSchedule(
            enabled: (bool) $this->settings->get($siteId, BackupSchedule::KEY_ENABLED, false),
            interval: BackupSchedule::normalizeInterval(is_string($interval) ? $interval : ''),
            retain: BackupSchedule::normalizeRetain(is_numeric($retain) ? (int) $retain : BackupSchedule::RETAIN_DEFAULT),
            includeMedia: (bool) $this->settings->get($siteId, BackupSchedule::KEY_INCLUDE_MEDIA, true),
            lastRunAt: $this->lastRunAt($siteId),
        );
    }

    /**
     * Stores the operator-editable fields; the last run stamp is owned by the runner.
     */
    public function save(SiteId $siteId, BackupSchedule $schedule): void
    {
        $this->settings->set($siteId, BackupSchedule::KEY_ENABLED, $schedule->enabled);
        $this->settings->set($siteId, BackupSchedule::KEY_INTERVAL, BackupSchedule::normalizeInterval($schedule->interval));
        $this->settings->set($siteId, BackupSchedule::KEY_RETAIN, BackupSchedule::normalizeRetain($schedule->retain));
        $this->settings->set($siteId, BackupSchedule::KEY_INCLUDE_MEDIA, $schedule->includeMedia);
    }

    public function markRan(SiteId $siteId, DateTimeImmutable $at): void
    {
        $this->settings->set(
            $siteId,
            BackupSchedule::KEY_LAST_RUN,
            $at->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        );
    }

    private function lastRunAt(SiteId $siteId): ?DateTimeImmutable
    {
        $raw = $this->settings->get($siteId, BackupSchedule::KEY_LAST_RUN);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }
    }
}
