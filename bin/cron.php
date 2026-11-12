<?php

declare(strict_types=1);

/**
 * Unified Nexis cron entry point (one crontab line).
 *
 * Runs per invocation:
 * - webhook / mail / plugin queue jobs
 * - due scheduled publish + unpublish
 * - audit / idempotency / forms retention purge
 * - due backup planner (Admin → Backups)
 *
 * Usage:
 *   php bin/cron.php
 *   composer cron
 *
 * Crontab example: docs/ops/crontab.example
 *
 * Exit codes: 0 = ok, 1 = fatal/lock/error, 75 = already running (EX_TEMPFAIL).
 */
use Nexis\Audit\AuditLogger;
use Nexis\Content\ScheduledPublishWorker;
use Nexis\Http\IdempotencyStore;
use Nexis\Infrastructure\Backup\BackupScheduleRunner;
use Nexis\Kernel\Bootstrap;
use Nexis\Kernel\Config;
use Nexis\Mail\MailQueueWorker;
use Nexis\Plugin\PluginRuntime;
use Nexis\Plugins\Forms\FormsRetentionPurge;
use Nexis\Queue\JobHandlerRegistry;
use Nexis\Support\Clock;
use Nexis\Webhook\WebhookDispatcher;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$lockDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0775, true);
}
$lockFile = $lockDir . DIRECTORY_SEPARATOR . 'cron.lock';
$lock = fopen($lockFile, 'c+');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "nexis cron already running or lock unavailable.\n");
    exit(75);
}

$exit = 0;
try {
    $app = Bootstrap::boot($root);
    $app->container->get(PluginRuntime::class)->bootOnce();
    $webhooks = $app->container->get(WebhookDispatcher::class)->processQueued(50);
    $mail = $app->container->get(MailQueueWorker::class)->processQueued(50);
    $pluginJobs = $app->container->get(JobHandlerRegistry::class)->processAll(50);
    $scheduled = $app->container->get(ScheduledPublishWorker::class)->processDue(50);
    $unpublished = $app->container->get(ScheduledPublishWorker::class)->processDueUnpublish(50);

    $config = $app->container->get(Config::class);
    $auditDays = $config->auditRetentionDays();
    $formsDays = $config->formsRetentionDays();
    $cutoff = $app->container->get(Clock::class)->now()->modify('-' . $auditDays . ' days');
    $purged = $app->container->get(AuditLogger::class)->purgeOlderThan($cutoff);
    $idemPurged = $app->container->get(IdempotencyStore::class)->purgeExpired();
    $formsPurged = $app->container->get(FormsRetentionPurge::class)->purgeOlderThanDays($formsDays);

    $backup = $app->container->get(BackupScheduleRunner::class)->runIfDue();

    fwrite(
        STDOUT,
        "Processed {$webhooks} webhook job(s), {$mail} mail job(s), {$pluginJobs} plugin job(s), {$scheduled} scheduled publish(es), "
        . "{$unpublished} scheduled unpublish(es), "
        . "purged {$purged} audit row(s), {$idemPurged} idempotency key(s), {$formsPurged} form submission(s).\n",
    );
    if ($backup->created()) {
        fwrite(STDOUT, "Scheduled backup {$backup->stamp} created, pruned " . count($backup->pruned) . " old backup(s).\n");
    } elseif ($backup->failed()) {
        fwrite(STDERR, 'Scheduled backup failed: ' . (string) $backup->error . "\n");
        $exit = 1;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'nexis cron failed: ' . $e->getMessage() . "\n");
    $exit = 1;
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}

exit($exit);
