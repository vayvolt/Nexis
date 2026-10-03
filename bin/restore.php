<?php

declare(strict_types=1);

/**
 * Restore a backup created by bin/backup.php / LogicalBackup.
 *
 * Usage:
 *   php bin/restore.php
 *   php bin/restore.php --latest --yes
 *   php bin/restore.php 20260928-091500 --yes
 *   php bin/restore.php 20260928-091500 --db-only --yes
 */
use Nexis\Infrastructure\Backup\LogicalBackup;
use Nexis\Kernel\Bootstrap;
use Nexis\Kernel\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$backup = new LogicalBackup($root);
$backupsRoot = $backup->backupsRoot();

$args = array_values(array_slice($argv, 1));
$yes = in_array('--yes', $args, true) || in_array('-y', $args, true);
$dbOnly = in_array('--db-only', $args, true);
$latest = in_array('--latest', $args, true);
$stamp = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '-')) {
        continue;
    }
    $stamp = $arg;
    break;
}

$available = listBackupStamps($backupsRoot);
if ($stamp === null && !$latest) {
    fwrite(STDOUT, "Vorhandene Backups unter storage/backups/:\n");
    if ($available === []) {
        fwrite(STDOUT, "  (keine)\n");
        fwrite(STDOUT, "Zuerst: php bin/backup.php\n");
        exit(0);
    }
    foreach ($available as $row) {
        fwrite(STDOUT, sprintf("  %s  %s\n", $row['stamp'], $row['created_at']));
    }
    fwrite(STDOUT, "\nRestore: php bin/restore.php {stamp}|--latest [--db-only] --yes\n");
    exit(0);
}

if ($latest) {
    $stamp = $available[0]['stamp'] ?? null;
}
if (!is_string($stamp) || $stamp === '') {
    fwrite(STDERR, "Ungültiger Backup-Stempel. Erwartet: YYYYMMDD-HHMMSS oder --latest\n");
    exit(1);
}

$app = Bootstrap::boot($root);
$config = $app->container->get(Config::class);
$db = [
    'host' => (string) $config->get('db.host'),
    'port' => (string) $config->get('db.port'),
    'database' => (string) $config->get('db.database'),
    'username' => (string) $config->get('db.username'),
    'password' => (string) $config->get('db.password'),
];

fwrite(STDOUT, "Restore: {$stamp}\n");
fwrite(STDOUT, "Datenbank: {$db['database']}@{$db['host']}\n");
fwrite(STDOUT, $dbOnly ? "Medien: übersprungen (--db-only)\n" : "Medien: storage/media ersetzen\n");

if (!$yes) {
    fwrite(STDERR, "Abbruch: destruktiv. Zum Fortfahren --yes anhängen.\n");
    exit(2);
}

try {
    $backup->restore($db, $stamp, !$dbOnly);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "Fertig. Smoke-Test: Startseite, /admin/login, Medien.\n");

/**
 * @return list<array{stamp: string, created_at: string}>
 */
function listBackupStamps(string $backupsRoot): array
{
    if (!is_dir($backupsRoot)) {
        return [];
    }
    $dirs = scandir($backupsRoot);
    if ($dirs === false) {
        return [];
    }
    $out = [];
    foreach ($dirs as $name) {
        if ($name === '.' || $name === '..' || preg_match('/^\d{8}-\d{6}$/', $name) !== 1) {
            continue;
        }
        $dir = $backupsRoot . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($dir) || !is_file($dir . DIRECTORY_SEPARATOR . 'database.sql')) {
            continue;
        }
        $created = '';
        $manifestFile = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (is_file($manifestFile)) {
            $decoded = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($decoded)) {
                $created = (string) ($decoded['created_at'] ?? '');
            }
        }
        $out[] = ['stamp' => $name, 'created_at' => $created];
    }
    usort($out, static fn (array $a, array $b): int => strcmp($b['stamp'], $a['stamp']));

    return $out;
}
