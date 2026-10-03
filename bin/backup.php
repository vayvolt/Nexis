<?php

declare(strict_types=1);

/**
 * Logical backup: MariaDB dump + storage/media → storage/backups/{timestamp}/
 *
 * Usage: php bin/backup.php [--no-media] [--scheduled]
 *
 * --no-media   dump the database only
 * --scheduled  honour the Planer from /admin/backups: run only when due and
 *              prune everything beyond the retention count (for cron)
 *
 * Optional: MYSQLDUMP_PATH=…
 *
 * Exit codes: 0 = ok (including "nothing to do"), 1 = backup failed.
 */
use Nexis\Infrastructure\Backup\BackupRunResult;
use Nexis\Infrastructure\Backup\BackupScheduleRunner;
use Nexis\Infrastructure\Backup\LogicalBackup;
use Nexis\Kernel\Bootstrap;
use Nexis\Kernel\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$app = Bootstrap::boot($root);
$config = $app->container->get(Config::class);
$args = array_slice($argv ?? [], 1);

if (in_array('--scheduled', $args, true)) {
    $result = $app->container->get(BackupScheduleRunner::class)->runIfDue();
    foreach ($result->pruned as $stamp) {
        fwrite(STDOUT, "Altes Backup entfernt: {$stamp}\n");
    }
    switch ($result->reason) {
        case BackupRunResult::REASON_CREATED:
            fwrite(STDOUT, "Geplantes Backup erstellt: {$result->stamp}\n");
            exit(0);
        case BackupRunResult::REASON_FAILED:
            fwrite(STDERR, (string) $result->error . "\n");
            fwrite(STDERR, "Unvollständiges Backup wurde verworfen.\n");
            exit(1);
        case BackupRunResult::REASON_DISABLED:
            fwrite(STDOUT, "Planer ist aus (Admin → Backups).\n");
            exit(0);
        case BackupRunResult::REASON_NOT_DUE:
            fwrite(STDOUT, "Noch nicht fällig.\n");
            exit(0);
        case BackupRunResult::REASON_LOCKED:
            fwrite(STDOUT, "Ein anderer Backup-Lauf ist aktiv.\n");
            exit(0);
        default:
            fwrite(STDERR, "Keine installierte Website gefunden.\n");
            exit(1);
    }
}

$backup = new LogicalBackup($root);
$includeMedia = !in_array('--no-media', $args, true);

try {
    $result = $backup->create([
        'host' => (string) $config->get('db.host'),
        'port' => (string) $config->get('db.port'),
        'database' => (string) $config->get('db.database'),
        'username' => (string) $config->get('db.username'),
        'password' => (string) $config->get('db.password'),
    ], (string) $config->get('app.url'), $includeMedia);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    fwrite(STDERR, "Unvollständiges Backup wurde verworfen.\n");
    exit(1);
}

fwrite(STDOUT, "Backup erstellt: {$result['path']}\n");
fwrite(STDOUT, "Vor CMS-Update empfohlen. Restore: php bin/restore.php {$result['stamp']} --yes\n");
fwrite(STDOUT, "Siehe docs/ops/restore.md\n");
