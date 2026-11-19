<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Cron;

use Nexis\Audit\AuditLogger;
use Nexis\Content\ScheduledPublishWorker;
use Nexis\Http\IdempotencyStore;
use Nexis\Infrastructure\Backup\BackupScheduleRunner;
use Nexis\Kernel\Config;
use Nexis\Mail\MailQueueWorker;
use Nexis\Plugin\PluginRuntime;
use Nexis\Plugins\Forms\FormsRetentionPurge;
use Nexis\Queue\JobHandlerRegistry;
use Nexis\Support\Clock;
use Nexis\Webhook\WebhookDispatcher;
use Psr\Container\ContainerInterface;
use Throwable;

/**
 * Shared cron body for CLI (`bin/cron.php`) and web (`cron.php`).
 */
final class CronRunner
{
    public function __construct(
        private Config $config,
        private ContainerInterface $container,
        private PluginRuntime $plugins,
        private WebhookDispatcher $webhooks,
        private MailQueueWorker $mail,
        private JobHandlerRegistry $jobs,
        private ScheduledPublishWorker $scheduled,
        private AuditLogger $audit,
        private IdempotencyStore $idempotency,
        private BackupScheduleRunner $backupSchedule,
        private Clock $clock,
    ) {
    }

    public function run(): CronRunResult
    {
        $lockDir = $this->config->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($lockDir) && !mkdir($lockDir, 0775, true) && !is_dir($lockDir)) {
            return CronRunResult::failed('cron lock directory unavailable');
        }
        $lockFile = $lockDir . DIRECTORY_SEPARATOR . 'cron.lock';
        $lock = fopen($lockFile, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            return CronRunResult::busy();
        }

        try {
            // Plugin PSR-4 (e.g. FormsRetentionPurge) is available only after boot.
            $this->plugins->bootOnce();
            $webhooks = $this->webhooks->processQueued(50);
            $mail = $this->mail->processQueued(50);
            $pluginJobs = $this->jobs->processAll(50);
            $scheduled = $this->scheduled->processDue(50);
            $unpublished = $this->scheduled->processDueUnpublish(50);

            $auditDays = $this->config->auditRetentionDays();
            $formsDays = $this->config->formsRetentionDays();
            $cutoff = $this->clock->now()->modify('-' . $auditDays . ' days');
            $purged = $this->audit->purgeOlderThan($cutoff);
            $idemPurged = $this->idempotency->purgeExpired();
            $formsPurged = $this->container->get(FormsRetentionPurge::class)->purgeOlderThanDays($formsDays);

            $backup = $this->backupSchedule->runIfDue();
            $lines = [
                "Processed {$webhooks} webhook job(s), {$mail} mail job(s), {$pluginJobs} plugin job(s), "
                . "{$scheduled} scheduled publish(es), {$unpublished} scheduled unpublish(es), "
                . "purged {$purged} audit row(s), {$idemPurged} idempotency key(s), {$formsPurged} form submission(s).",
            ];
            if ($backup->created()) {
                $lines[] = 'Scheduled backup ' . $backup->stamp . ' created, pruned '
                    . count($backup->pruned) . ' old backup(s).';
            } elseif ($backup->failed()) {
                return CronRunResult::failed(
                    'Scheduled backup failed: ' . (string) $backup->error,
                    $lines,
                );
            }

            return CronRunResult::ok($lines);
        } catch (Throwable $e) {
            return CronRunResult::failed('nexis cron failed: ' . $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
