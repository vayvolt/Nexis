<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use RuntimeException;
use Throwable;

/**
 * Logical MariaDB dump + storage/media → storage/backups/{stamp}.zip.
 * Legacy exploded directories under storage/backups/{stamp}/ remain readable.
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
        $root = $this->backupsRoot();
        if (!is_dir($root) && !@mkdir($root, 0775, true) && !is_dir($root)) {
            throw new RuntimeException('Backup directory could not be created.');
        }

        $workDir = $root . DIRECTORY_SEPARATOR . $stamp;
        $zipPath = $this->zipPathFor($stamp);
        if ($zipPath === null || is_dir($workDir) || is_file($zipPath) || !@mkdir($workDir, 0775, true)) {
            throw new RuntimeException('Backup workspace could not be created: ' . $stamp);
        }

        try {
            $mysqldump = $this->resolveMysqldump();
            if ($mysqldump === null) {
                throw new RuntimeException(
                    'mysqldump not found. Set MYSQLDUMP_PATH or add mysqldump to PATH.',
                );
            }

            $sqlFile = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
            // No --routines: MariaDB upgrades often break mysql.proc until
            // mariadb-upgrade runs; Nexis core does not ship stored routines.
            $args = [
                $mysqldump,
                '--host=' . $db['host'],
                '--port=' . ($db['port'] !== '' ? $db['port'] : '3306'),
                '--user=' . $db['username'],
                '--single-transaction',
                '--triggers',
                '--result-file=' . $sqlFile,
                $db['database'],
            ];
            if ($db['password'] !== '') {
                array_splice($args, 4, 0, '--password=' . $db['password']);
            }

            $ran = $this->runCommand($args);
            if ($ran['code'] !== 0 || !is_file($sqlFile) || filesize($sqlFile) <= 0) {
                $detail = $ran['output'] !== '' ? ' ' . $ran['output'] : '';
                throw new RuntimeException('mysqldump failed (exit ' . $ran['code'] . ').' . $detail);
            }

            $mediaSrc = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media';
            $mediaDst = $workDir . DIRECTORY_SEPARATOR . 'media';
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
                'format' => 'zip',
            ];
            file_put_contents(
                $workDir . DIRECTORY_SEPARATOR . 'manifest.json',
                json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );

            $this->packDirectoryToZip($workDir, $zipPath);
            $this->removeTree($workDir);

            if (!is_file($zipPath) || filesize($zipPath) <= 0) {
                throw new RuntimeException('Backup ZIP could not be written: ' . $stamp);
            }

            return ['stamp' => $stamp, 'path' => $zipPath];
        } catch (Throwable $e) {
            $this->removeTree($workDir);
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            throw $e;
        }
    }

    /**
     * @param array{host: string, port: string, database: string, username: string, password: string} $db
     */
    public function restore(array $db, string $stamp, bool $withMedia = true): void
    {
        if (!self::isStamp($stamp)) {
            throw new RuntimeException('Invalid backup stamp.');
        }

        $extractDir = null;
        try {
            $backupDir = $this->materialize($stamp, $extractDir);
            $sqlFile = $backupDir . DIRECTORY_SEPARATOR . 'database.sql';
            if (!is_file($sqlFile) || filesize($sqlFile) <= 0) {
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
        } finally {
            if ($extractDir !== null) {
                $this->removeTree($extractDir);
            }
        }
    }

    public function backupsRoot(): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
    }

    /**
     * Stamps have second resolution, so a manual and a scheduled backup can
     * collide. Advance until a free one is found instead of writing two dumps
     * into the same stamp (directory or ZIP).
     */
    private function freeStamp(): string
    {
        $now = time();
        for ($offset = 0; $offset < 120; $offset++) {
            $stamp = gmdate('Ymd-His', $now + $offset);
            if (!$this->exists($stamp)) {
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

    /**
     * Absolute ZIP path for a stamp (`{stamp}.zip`), or null when invalid.
     */
    public function zipPathFor(string $stamp): ?string
    {
        if (!self::isStamp($stamp)) {
            return null;
        }

        return $this->backupsRoot() . DIRECTORY_SEPARATOR . $stamp . '.zip';
    }

    public function exists(string $stamp): bool
    {
        $dir = $this->pathFor($stamp);
        $zip = $this->zipPathFor($stamp);

        return ($dir !== null && is_dir($dir)) || ($zip !== null && is_file($zip));
    }

    public function delete(string $stamp): bool
    {
        $deletedDir = false;
        $deletedZip = false;
        $dir = $this->pathFor($stamp);
        if ($dir !== null && is_dir($dir)) {
            $deletedDir = $this->removeTree($dir);
        }
        $zip = $this->zipPathFor($stamp);
        if ($zip !== null && is_file($zip)) {
            $deletedZip = @unlink($zip);
        }

        return $deletedDir || $deletedZip;
    }

    /**
     * Resolve a stamp to a directory with database.sql (legacy dir or extracted ZIP).
     * When `$extractDir` is set, the caller must delete it afterwards.
     */
    private function materialize(string $stamp, ?string &$extractDir): string
    {
        $zip = $this->zipPathFor($stamp);
        if ($zip !== null && is_file($zip)) {
            $extractDir = $this->extractZip($zip, $stamp);

            return $this->normalizeBackupRoot($extractDir, $stamp);
        }

        $dir = $this->pathFor($stamp);
        if ($dir !== null && is_dir($dir)) {
            return $this->normalizeBackupRoot($dir, $stamp);
        }

        throw new RuntimeException('Backup missing: ' . $stamp);
    }

    /**
     * Accept both flat ZIPs (`database.sql`) and older download ZIPs (`{stamp}/database.sql`).
     */
    private function normalizeBackupRoot(string $dir, string $stamp): string
    {
        if (is_file($dir . DIRECTORY_SEPARATOR . 'database.sql')) {
            return $dir;
        }
        $nested = $dir . DIRECTORY_SEPARATOR . $stamp;
        if (is_file($nested . DIRECTORY_SEPARATOR . 'database.sql')) {
            return $nested;
        }

        return $dir;
    }

    private function packDirectoryToZip(string $dir, string $zipPath): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('ext-zip fehlt für Backup-Archive.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }

        $prefix = strlen($dir) + 1;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), $prefix));
            if ($relative === '' || $file->getFilename() === '.env' || str_starts_with($file->getFilename(), '.env.')) {
                continue;
            }
            if ($file->isDir()) {
                $zip->addEmptyDir($relative);
            } elseif ($file->isFile()) {
                $zip->addFile($file->getPathname(), $relative);
            }
        }

        if (!$zip->close()) {
            throw new RuntimeException('ZIP konnte nicht geschrieben werden.');
        }
    }

    private function extractZip(string $zipPath, string $stamp): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('ext-zip fehlt für Restore aus ZIP.');
        }
        $target = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'backup-restore-' . $stamp . '-' . bin2hex(random_bytes(4));
        if (!@mkdir($target, 0775, true) && !is_dir($target)) {
            throw new RuntimeException('Restore-Tempverzeichnis fehlt.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->removeTree($target);
            throw new RuntimeException('Backup-ZIP konnte nicht geöffnet werden: ' . $stamp);
        }
        if (!$zip->extractTo($target)) {
            $zip->close();
            $this->removeTree($target);
            throw new RuntimeException('Backup-ZIP konnte nicht entpackt werden: ' . $stamp);
        }
        $zip->close();

        return $target;
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
     * @return array{code: int, output: string}
     */
    private function runCommand(array $args): array
    {
        $cmd = implode(' ', array_map(static fn (string $a): string => escapeshellarg($a), $args));
        $output = [];
        exec($cmd . ' 2>&1', $output, $code);
        $text = trim(implode("\n", $output));
        // Keep flash/redirect URLs readable; full log still has the first lines.
        if (strlen($text) > 400) {
            $text = substr($text, 0, 397) . '...';
        }

        return ['code' => $code, 'output' => $text];
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
