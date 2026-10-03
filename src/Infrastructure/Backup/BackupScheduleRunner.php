<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use Nexis\Kernel\Config;
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;
use Nexis\Support\Clock;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs the configured backup Planer: creates a backup when it is due and prunes
 * everything beyond the retention count. Called from bin/queue-work.php (once per
 * process) and from `php bin/backup.php --scheduled`.
 */
final class BackupScheduleRunner
{
    public function __construct(
        private SiteRepository $sites,
        private BackupScheduleStore $schedules,
        private BackupCatalog $catalog,
        private LogicalBackup $backup,
        private Config $config,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function runIfDue(bool $force = false): BackupRunResult
    {
        $site = $this->sites->installed();
        if ($site === null) {
            return new BackupRunResult(BackupRunResult::REASON_NO_SITE);
        }

        $schedule = $this->schedules->load($site->id);
        if (!$schedule->enabled) {
            return new BackupRunResult(BackupRunResult::REASON_DISABLED);
        }
        if (!$force && !$this->due($schedule)) {
            return new BackupRunResult(BackupRunResult::REASON_NOT_DUE);
        }

        $lock = $this->acquireLock();
        if ($lock === null) {
            return new BackupRunResult(BackupRunResult::REASON_LOCKED);
        }

        try {
            // Re-read inside the lock: a parallel worker may just have run.
            $schedule = $this->schedules->load($site->id);
            if (!$force && !$this->due($schedule)) {
                return new BackupRunResult(BackupRunResult::REASON_NOT_DUE);
            }

            // Record the attempt before dumping: a persistently failing backup
            // (no mysqldump, full disk) must not retry on every worker run.
            $this->schedules->markRan($site->id, $this->clock->now());

            $created = $this->backup->create(
                $this->databaseConfig(),
                (string) $this->config->get('app.url'),
                $schedule->includeMedia,
            );
            $pruned = $this->prune($site->id, $schedule->retain);

            $this->logger->info('Scheduled backup created.', [
                'stamp' => $created['stamp'],
                'pruned' => count($pruned),
            ]);

            return new BackupRunResult(BackupRunResult::REASON_CREATED, $created['stamp'], $pruned);
        } catch (Throwable $e) {
            $this->logger->error('Scheduled backup failed: ' . $e->getMessage());

            return new BackupRunResult(BackupRunResult::REASON_FAILED, error: $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * A schedule that never ran falls back to the newest existing backup, so
     * switching the Planer on right after a manual backup does not immediately
     * produce a second one.
     */
    private function due(BackupSchedule $schedule): bool
    {
        $lastRun = $schedule->lastRunAt ?? $this->catalog->latest()?->createdAt;

        return $schedule->withLastRunAt($lastRun)->isDue($this->clock->now());
    }

    /**
     * Deletes the oldest backups beyond the retention count.
     *
     * @return list<string> deleted stamps, oldest first
     */
    public function prune(SiteId $siteId, ?int $retain = null): array
    {
        $retain ??= $this->schedules->load($siteId)->retain;
        $deleted = [];
        foreach (BackupSchedule::prunable($this->catalog->stamps(), $retain) as $stamp) {
            if ($this->backup->delete($stamp)) {
                $deleted[] = $stamp;
            }
        }

        return $deleted;
    }

    /**
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    private function databaseConfig(): array
    {
        return [
            'host' => (string) $this->config->get('db.host'),
            'port' => (string) $this->config->get('db.port'),
            'database' => (string) $this->config->get('db.database'),
            'username' => (string) $this->config->get('db.username'),
            'password' => (string) $this->config->get('db.password'),
        ];
    }

    /**
     * @return resource|null
     */
    private function acquireLock()
    {
        $dir = $this->config->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $handle = fopen($dir . DIRECTORY_SEPARATOR . 'backup-schedule.lock', 'c+');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }
}
