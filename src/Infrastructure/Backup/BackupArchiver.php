<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Serves backup ZIPs for download. New backups are already `{stamp}.zip`;
 * legacy directories are packed into storage/tmp/ on demand.
 */
final class BackupArchiver
{
    private const STALE_SECONDS = 3600;

    public function __construct(
        private LogicalBackup $backup,
        private string $tmpPath,
    ) {
        $this->tmpPath = rtrim($tmpPath, '\\/');
    }

    /**
     * @return string absolute path of the archive to stream
     */
    public function zip(string $stamp): string
    {
        $stored = $this->backup->zipPathFor($stamp);
        if ($stored !== null && is_file($stored)) {
            return $stored;
        }

        $dir = $this->backup->pathFor($stamp);
        if ($dir === null || !is_dir($dir)) {
            throw new RuntimeException('Backup missing: ' . $stamp);
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ext-zip fehlt für den Backup-Download.');
        }
        if (!is_dir($this->tmpPath) && !mkdir($this->tmpPath, 0775, true) && !is_dir($this->tmpPath)) {
            throw new RuntimeException('Temp-Verzeichnis fehlt: ' . $this->tmpPath);
        }
        $this->pruneStale();

        $target = $this->tmpPath . DIRECTORY_SEPARATOR
            . 'nexis-backup-' . $stamp . '-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP konnte nicht erstellt werden.');
        }

        $prefix = strlen($dir) + 1;
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        ) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), $prefix));
            if ($relative === '' || $this->isSecret($file->getFilename())) {
                continue;
            }
            if ($file->isDir()) {
                $zip->addEmptyDir($relative);
            } elseif ($file->isFile()) {
                $zip->addFile($file->getPathname(), $relative);
            }
        }

        if (!$zip->close()) {
            @unlink($target);

            throw new RuntimeException('ZIP konnte nicht geschrieben werden.');
        }

        return $target;
    }

    /**
     * Absolute path to database.sql for download (legacy dir or extracted from ZIP).
     */
    public function sqlFile(string $stamp): string
    {
        $dir = $this->backup->pathFor($stamp);
        if ($dir !== null && is_file($dir . DIRECTORY_SEPARATOR . 'database.sql')) {
            return $dir . DIRECTORY_SEPARATOR . 'database.sql';
        }

        $stored = $this->backup->zipPathFor($stamp);
        if ($stored === null || !is_file($stored) || !class_exists(ZipArchive::class)) {
            throw new RuntimeException('Backup dump missing: ' . $stamp);
        }
        if (!is_dir($this->tmpPath) && !mkdir($this->tmpPath, 0775, true) && !is_dir($this->tmpPath)) {
            throw new RuntimeException('Temp-Verzeichnis fehlt: ' . $this->tmpPath);
        }
        $this->pruneStale();

        $zip = new ZipArchive();
        if ($zip->open($stored) !== true) {
            throw new RuntimeException('Backup-ZIP konnte nicht geöffnet werden.');
        }
        $name = null;
        foreach (['database.sql', $stamp . '/database.sql'] as $candidate) {
            if ($zip->locateName($candidate) !== false) {
                $name = $candidate;
                break;
            }
        }
        if ($name === null) {
            $zip->close();
            throw new RuntimeException('database.sql fehlt im Backup.');
        }
        $raw = $zip->getFromName($name);
        $zip->close();
        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('database.sql leer.');
        }

        $target = $this->tmpPath . DIRECTORY_SEPARATOR
            . 'nexis-backup-' . $stamp . '-' . bin2hex(random_bytes(4)) . '.sql';
        if (file_put_contents($target, $raw) === false) {
            throw new RuntimeException('Dump konnte nicht extrahiert werden.');
        }

        return $target;
    }

    /**
     * Backups never contain environment files; this keeps it that way even if a
     * stray copy ends up in the directory.
     */
    private function isSecret(string $filename): bool
    {
        return $filename === '.env' || str_starts_with($filename, '.env.');
    }

    private function pruneStale(): void
    {
        foreach (['nexis-backup-*.zip', 'nexis-backup-*.sql'] as $pattern) {
            $items = glob($this->tmpPath . DIRECTORY_SEPARATOR . $pattern);
            if ($items === false) {
                continue;
            }
            $cutoff = time() - self::STALE_SECONDS;
            foreach ($items as $item) {
                $mtime = filemtime($item);
                if ($mtime !== false && $mtime < $cutoff) {
                    @unlink($item);
                }
            }
        }
    }
}
