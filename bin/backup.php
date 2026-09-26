<?php

declare(strict_types=1);

/**
 * Logical backup: MariaDB dump + storage/media copy into storage/backups/{timestamp}/
 *
 * Usage: php bin/backup.php
 *
 * Optional: MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe
 */
use Nexis\Kernel\Bootstrap;
use Nexis\Kernel\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$app = Bootstrap::boot($root);
$config = $app->container->get(Config::class);

$stamp = gmdate('Ymd-His');
$backupRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $stamp;
if (!mkdir($backupRoot, 0775, true) && !is_dir($backupRoot)) {
    fwrite(STDERR, "Backup-Verzeichnis konnte nicht angelegt werden.\n");
    exit(1);
}

$dbHost = (string) $config->get('db.host');
$dbPort = (string) $config->get('db.port');
$dbName = (string) $config->get('db.database');
$dbUser = (string) $config->get('db.username');
$dbPass = (string) $config->get('db.password');
$sqlFile = $backupRoot . DIRECTORY_SEPARATOR . 'database.sql';

$mysqldump = resolveMysqldump();
if ($mysqldump === null) {
    fwrite(STDERR, "mysqldump nicht gefunden (PATH und übliche XAMPP-Pfade leer).\n");
    fwrite(STDERR, "Tipp: MYSQLDUMP_PATH setzen (voller Pfad zu mysqldump), oder mysqldump in den PATH legen.\n");
    exit(1);
}

$args = [
    $mysqldump,
    '--host=' . $dbHost,
    '--port=' . $dbPort,
    '--user=' . $dbUser,
    '--single-transaction',
    '--routines',
    '--triggers',
    '--result-file=' . $sqlFile,
    $dbName,
];
if ($dbPass !== '') {
    // Avoid shell history leakage; still visible in process list — same as before.
    array_splice($args, 4, 0, '--password=' . $dbPass);
}

$code = runCommand($args);
$dbOk = $code === 0 && is_file($sqlFile) && filesize($sqlFile) > 0;
if (!$dbOk) {
    fwrite(STDERR, "mysqldump fehlgeschlagen (Exit {$code}) mit: {$mysqldump}\n");
    fwrite(STDERR, "Manuell: \"{$mysqldump}\" --single-transaction --result-file=database.sql {$dbName}\n");
}

$mediaSrc = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media';
$mediaDst = $backupRoot . DIRECTORY_SEPARATOR . 'media';
$mediaOk = true;
try {
    if (is_dir($mediaSrc)) {
        copyTree($mediaSrc, $mediaDst);
    }
} catch (Throwable $e) {
    $mediaOk = false;
    fwrite(STDERR, 'Medien-Kopie fehlgeschlagen: ' . $e->getMessage() . "\n");
}

$meta = [
    'created_at' => gmdate('c'),
    'database' => $dbName,
    'app_url' => $config->get('app.url'),
    'includes' => ['database.sql', 'media/'],
    'database_ok' => $dbOk,
    'media_ok' => $mediaOk,
    'mysqldump' => $mysqldump,
];
file_put_contents(
    $backupRoot . DIRECTORY_SEPARATOR . 'manifest.json',
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
);

if (!$dbOk) {
    fwrite(STDERR, "Backup unvollständig: {$backupRoot}\n");
    exit(1);
}

fwrite(STDOUT, "Backup erstellt: {$backupRoot}\n");
fwrite(STDOUT, "Restore: siehe docs/ops/restore.md\n");

/**
 * @return non-empty-string|null
 */
function resolveMysqldump(): ?string
{
    $env = getenv('MYSQLDUMP_PATH');
    if (is_string($env) && $env !== '' && is_file($env)) {
        return $env;
    }

    $which = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'command -v mysqldump';
    $found = [];
    exec($which . ' 2>NUL', $found, $code);
    if ($code === 0) {
        foreach ($found as $line) {
            $line = trim($line);
            if ($line !== '' && is_file($line)) {
                return $line;
            }
        }
    }

    $candidates = [];
    if (PHP_OS_FAMILY === 'Windows') {
        foreach (['C:', 'D:', 'E:'] as $drive) {
            $candidates[] = $drive . '\\xampp\\mysql\\bin\\mysqldump.exe';
            $candidates[] = $drive . '\\xampp\\mariadb\\bin\\mysqldump.exe';
            $candidates[] = $drive . '\\XAMPP\\mysql\\bin\\mysqldump.exe';
        }
        $candidates[] = 'C:\\Program Files\\MariaDB 11.4\\bin\\mysqldump.exe';
        $candidates[] = 'C:\\Program Files\\MariaDB 10.11\\bin\\mysqldump.exe';
        $candidates[] = 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe';
    } else {
        $candidates[] = '/usr/bin/mysqldump';
        $candidates[] = '/usr/local/bin/mysqldump';
        $candidates[] = '/usr/local/mysql/bin/mysqldump';
    }

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * @param list<string> $args
 */
function runCommand(array $args): int
{
    $cmd = implode(' ', array_map(static fn (string $a): string => escapeshellarg($a), $args));
    $output = [];
    exec($cmd . ' 2>&1', $output, $code);
    if ($code !== 0 && $output !== []) {
        fwrite(STDERR, implode("\n", $output) . "\n");
    }

    return $code;
}

function copyTree(string $src, string $dst): void
{
    if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
        throw new RuntimeException('mkdir failed: ' . $dst);
    }
    $items = scandir($src);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $src . DIRECTORY_SEPARATOR . $item;
        $to = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($from)) {
            copyTree($from, $to);
        } else {
            copy($from, $to);
        }
    }
}
