<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use DateTimeImmutable;
use DateTimeZone;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Reads storage/backups/ for the admin UI and the schedule runner.
 */
final class BackupCatalog
{
    public function __construct(
        private LogicalBackup $backup,
    ) {
    }

    /**
     * Newest first.
     *
     * @return list<BackupEntry>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->stamps() as $stamp) {
            $entry = $this->find($stamp);
            if ($entry !== null) {
                $out[] = $entry;
            }
        }

        return $out;
    }

    /**
     * Well-formed stamp directories, newest first. Anything else in
     * storage/backups/ is ignored so it can never be pruned or deleted.
     *
     * @return list<string>
     */
    public function stamps(): array
    {
        $root = $this->backup->backupsRoot();
        if (!is_dir($root)) {
            return [];
        }
        $items = scandir($root);
        if ($items === false) {
            return [];
        }

        $stamps = [];
        foreach ($items as $item) {
            if (LogicalBackup::isStamp($item) && is_dir($root . DIRECTORY_SEPARATOR . $item)) {
                $stamps[] = $item;
            }
        }
        rsort($stamps, SORT_STRING);

        return $stamps;
    }

    public function find(string $stamp): ?BackupEntry
    {
        $dir = $this->backup->pathFor($stamp);
        if ($dir === null || !is_dir($dir)) {
            return null;
        }

        $sqlFile = $dir . DIRECTORY_SEPARATOR . 'database.sql';
        $mediaDir = $dir . DIRECTORY_SEPARATOR . 'media';
        $hasDatabase = is_file($sqlFile);
        $hasMedia = is_dir($mediaDir);

        return new BackupEntry(
            stamp: $stamp,
            path: $dir,
            createdAt: $this->createdAt($stamp, $dir),
            bytes: $this->directoryBytes($dir),
            hasDatabase: $hasDatabase,
            databaseBytes: $hasDatabase ? (int) filesize($sqlFile) : 0,
            hasMedia: $hasMedia,
            mediaFiles: $hasMedia ? $this->fileCount($mediaDir) : 0,
            manifest: $this->manifest($dir),
        );
    }

    public function latest(): ?BackupEntry
    {
        $stamps = $this->stamps();

        return $stamps === [] ? null : $this->find($stamps[0]);
    }

    public function totalBytes(): int
    {
        $sum = 0;
        foreach ($this->all() as $entry) {
            $sum += $entry->bytes;
        }

        return $sum;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manifest(string $dir): ?array
    {
        $file = $dir . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($file)) {
            return null;
        }
        try {
            $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The stamp itself is the UTC creation time; filemtime is only a fallback.
     */
    private function createdAt(string $stamp, string $dir): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('Ymd-His', $stamp, new DateTimeZone('UTC'));
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
        $mtime = filemtime($dir);

        return new DateTimeImmutable('@' . ($mtime === false ? 0 : $mtime));
    }

    private function directoryBytes(string $dir): int
    {
        $bytes = 0;
        foreach ($this->walk($dir) as $file) {
            if ($file->isFile()) {
                $bytes += (int) $file->getSize();
            }
        }

        return $bytes;
    }

    private function fileCount(string $dir): int
    {
        $count = 0;
        foreach ($this->walk($dir) as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function walk(string $dir): iterable
    {
        if (!is_dir($dir)) {
            return [];
        }

        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
    }
}
