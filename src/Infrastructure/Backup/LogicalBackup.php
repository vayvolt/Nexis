<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use RuntimeException;
use Throwable;

/**
 * Logical MariaDB dump + storage/media copy under storage/backups/{stamp}/.
 * Used by CLI (bin/backup.php, bin/restore.php), the admin UI and the web installer wipe path.
 */
final class LogicalBackup
{
    public const STAMP_PATTERN = '/^\d{8}-\d{6}$/';

    public function __construct(
        private string $rootPath,
    ) {
        $this->rootPath = rtrim($rootPath, '\\/');
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $db
     * @return array{stamp: string, path: string}
     */
    public function create(array $db, ?string $appUrl = null, bool $includeMedia = true): array
    {
        $stamp = $this->freeStamp();
        $backupRoot = $this->backupsRoot() . DIRECTORY_SEPARATOR . $stamp;
        if (is_dir($backupRoot) || !@mkdir($backupRoot, 0775, true)) {
            throw new RuntimeException('Backup directory could not be created: ' . $stamp);
        }

        try {
            $mysqldump = $this->resolveMysqldump();
            if ($mysqldump === null) {
                throw new RuntimeException(
                    'mysqldump not found. Set MYSQLDUMP_PATH or add mysqldump to PATH.',
                );
            }

            $sqlFile = $backupRoot . DIRECTORY_SEPARATOR . 'database.sql';
            $args = [
                $mysqldump,
                '--host=' . $db['host'],
                '--port=' . ($db['port'] !== '' ? $db['port'] : '3306'),
                '--user=' . $db['username'],
                '--single-transaction',
                '--routines',
                '--triggers',
                '--result-file=' . $sqlFile,
                $db['database'],
            ];
            if ($db['password'] !== '') {
                array_splice($args, 4, 0, '--password=' . $db['password']);
            }

            $code = $this->runCommand($args);
            if ($code !== 0 || !is_file($sqlFile) || filesize($sqlFile) <= 0) {
                throw new RuntimeException('mysqldump failed (exit ' . $code . ').');
            }

            $mediaSrc = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media';
            $mediaDst = $backupRoot . DIRECTORY_SEPARATOR . 'media';
            $mediaCopied = false;
            if ($includeMedia && is_dir($mediaSrc)) {
                $this->copyTree($mediaSrc, $mediaDst);
                $mediaCopied = true;
            }

            $includes = ['database.sql'];
            if ($mediaCopied) {
                $includes[] = 'media/';
            }
            $meta = [
                'created_at' => gmdate('c'),
                'database' => $db['database'],
                'app_url' => $appUrl,
                'includes' => $includes,
                'database_ok' => true,
                'media_ok' => $includeMedia,
                'mysqldump' => $mysqldump,
            ];
            file_put_contents(
                $backupRoot . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );

            return ['stamp' => $stamp, 'path' => $backupRoot];
        } catch (Throwable $e) {
            $this->removeTree($backupRoot);
            throw $e;
        }
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $db
     */
    public function restore(array $db, string $stamp, bool $withMedia = true): void
    {
        $backupDir = $this->pathFor($stamp);
        if ($backupDir === null) {
            throw new RuntimeException('Invalid backup stamp.');
        }

        $sqlFile = $backupDir . DIRECTORY_SEPARATOR . 'database.sql';
        if (!is_dir($backupDir) || !is_file($sqlFile) || filesize($sqlFile) <= 0) {
            throw new RuntimeException('Backup missing or database.sql empty: ' . $stamp);
        }

        $manifestFile = $backupDir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (is_file($manifestFile)) {
            $decoded = json_decode((string) file_get_contents($manifestFile), true);
            if (is_array($decoded) && array_key_exists('database_ok', $decoded) && $decoded['database_ok'] !== true) {
                throw new RuntimeException('Backup marked incomplete in manifest.');
            }
        }

        $mysql = $this->resolveMysql();
        if ($mysql === null) {
            throw new RuntimeException('mysql client not found. Set MYSQL_PATH or add mysql to PATH.');
        }

        $args = [
            $mysql,
            '--host=' . $db['host'],
            '--port=' . ($db['port'] !== '' ? $db['port'] : '3306'),
            '--user=' . $db['username'],
        ];
        if ($db['password'] !== '') {
            $args[] = '--password=' . $db['password'];
        }
        $args[] = $db['database'];

        $code = $this->runCommandWithStdin($args, $sqlFile);
        if ($code !== 0) {
            throw new RuntimeException('mysql restore failed (exit ' . $code . ').');
        }

        if ($withMedia) {
            $mediaSrc = $backupDir . DIRECTORY_SEPARATOR . 'media';
            $mediaDst = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media';
            if (is_dir($mediaSrc)) {
                if (is_dir($mediaDst)) {
                    $this->removeTree($mediaDst);
                }
                $this->copyTree($mediaSrc, $mediaDst);
            }
        }

        $pageCache = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'pages';
        if (is_dir($pageCache)) {
            $this->removeTree($pageCache);
            @mkdir($pageCache, 0775, true);
        }
    }

    public function backupsRoot(): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
    }

    /**
     * Stamps have second resolution, so a manual and a scheduled backup can
     * collide. Advance until a free one is found instead of writing two dumps
     * into the same directory.
     */
    private function freeStamp(): string
    {
        $now = time();
        for ($offset = 0; $offset < 120; $offset++) {
            $stamp = gmdate('Ymd-His', $now + $offset);
            if (!is_dir($this->backupsRoot() . DIRECTORY_SEPARATOR . $stamp)) {
                return $stamp;
            }
        }

        throw new RuntimeException('No free backup stamp available.');
    }

    public static function isStamp(string $stamp): bool
    {
        return preg_match(self::STAMP_PATTERN, $stamp) === 1;
    }

    /**
     * Absolute directory for a stamp, or null when the stamp is not well-formed.
     * Keeps path traversal out of every caller that accepts a stamp from input.
     */
    public function pathFor(string $stamp): ?string
    {
        if (!self::isStamp($stamp)) {
            return null;
        }

        return $this->backupsRoot() . DIRECTORY_SEPARATOR . $stamp;
    }

    public function delete(string $stamp): bool
    {
        $dir = $this->pathFor($stamp);
        if ($dir === null || !is_dir($dir)) {
            return false;
        }
        return $this->removeTree($dir);
    }

    /**
     * @return non-empty-string|null
     */
    public function resolveMysqldump(): ?string
    {
        $env = getenv('MYSQLDUMP_PATH');
        if (is_string($env) && $env !== '' && is_file($env)) {
            return $env;
        }

        return $this->resolveBinary('mysqldump', [
            'Windows' => [
                '{drive}\\xampp\\mysql\\bin\\mysqldump.exe',
                '{drive}\\xampp\\mariadb\\bin\\mysqldump.exe',
                '{drive}\\XAMPP\\mysql\\bin\\mysqldump.exe',
                'C:\\Program Files\\MariaDB 11.4\\bin\\mysqldump.exe',
                'C:\\Program Files\\MariaDB 10.11\\bin\\mysqldump.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            ],
            'Unix' => [
                '/usr/bin/mysqldump',
                '/usr/local/bin/mysqldump',
                '/usr/local/mysql/bin/mysqldump',
            ],
        ]);
    }

    /**
     * @return non-empty-string|null
     */
    public function resolveMysql(): ?string
    {
        $env = getenv('MYSQL_PATH');
        if (is_string($env) && $env !== '' && is_file($env)) {
            return $env;
        }

        $dump = getenv('MYSQLDUMP_PATH');
        if (is_string($dump) && $dump !== '') {
            $sibling = dirname($dump) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'mysql.exe' : 'mysql');
            if (is_file($sibling)) {
                return $sibling;
            }
        }

        return $this->resolveBinary('mysql', [
            'Windows' => [
                '{drive}\\xampp\\mysql\\bin\\mysql.exe',
                '{drive}\\xampp\\mariadb\\bin\\mysql.exe',
                '{drive}\\XAMPP\\mysql\\bin\\mysql.exe',
                'C:\\Program Files\\MariaDB 11.4\\bin\\mysql.exe',
                'C:\\Program Files\\MariaDB 10.11\\bin\\mysql.exe',
                'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            ],
            'Unix' => [
                '/usr/bin/mysql',
                '/usr/local/bin/mysql',
                '/usr/local/mysql/bin/mysql',
            ],
        ]);
    }

    /**
     * @param array{Windows: list<string>, Unix: list<string>} $candidates
     * @return non-empty-string|null
     */
    private function resolveBinary(string $name, array $candidates): ?string
    {
        $which = PHP_OS_FAMILY === 'Windows' ? 'where ' . $name : 'command -v ' . $name;
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

        $paths = [];
        if (PHP_OS_FAMILY === 'Windows') {
            foreach ($candidates['Windows'] as $pattern) {
                if (str_contains($pattern, '{drive}')) {
                    foreach (['C:', 'D:', 'E:'] as $drive) {
                        $paths[] = str_replace('{drive}', $drive, $pattern);
                    }
                } else {
                    $paths[] = $pattern;
                }
            }
        } else {
            $paths = $candidates['Unix'];
        }

        foreach ($paths as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @param list<string> $args
     */
    private function runCommand(array $args): int
    {
        $cmd = implode(' ', array_map(static fn (string $a): string => escapeshellarg($a), $args));
        $output = [];
        exec($cmd . ' 2>&1', $output, $code);

        return $code;
    }

    /**
     * @param list<string> $args
     */
    private function runCommandWithStdin(array $args, string $stdinFile): int
    {
        $descriptors = [
            0 => ['file', $stdinFile, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($args, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            return 1;
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($proc);
    }

    private function copyTree(string $src, string $dst): void
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
                $this->copyTree($from, $to);
            } else {
                copy($from, $to);
            }
        }
    }

    private function removeTree(string $path): bool
    {
        if (!is_dir($path) && !is_file($path)) {
            return true;
        }
        if (is_file($path) || is_link($path)) {
            return @unlink($path);
        }
        $items = scandir($path);
        if ($items === false) {
            return false;
        }
        $ok = true;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $ok = $this->removeTree($path . DIRECTORY_SEPARATOR . $item) && $ok;
        }

        return @rmdir($path) && $ok;
    }
}
