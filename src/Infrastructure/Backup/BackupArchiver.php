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
 * Packs one backup stamp into a ZIP for download. Archives are built in
 * storage/tmp/ and cleaned up on the next download request.
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
     * @return string absolute path of the created archive
     */
    public function zip(string $stamp): string
    {
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
                $zip->addEmptyDir($stamp . '/' . $relative);
            } elseif ($file->isFile()) {
                $zip->addFile($file->getPathname(), $stamp . '/' . $relative);
            }
        }

        if (!$zip->close()) {
            @unlink($target);

            throw new RuntimeException('ZIP konnte nicht geschrieben werden.');
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
        $items = glob($this->tmpPath . DIRECTORY_SEPARATOR . 'nexis-backup-*.zip');
        if ($items === false) {
            return;
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
