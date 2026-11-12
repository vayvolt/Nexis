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
use ZipArchive;

/**
 * Reads storage/backups/ for the admin UI and the schedule runner.
 * Prefers `{stamp}.zip`; still lists legacy `{stamp}/` directories.
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
     * Well-formed stamp directories or ZIPs, newest first. Anything else in
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
                $stamps[$item] = true;
                continue;
            }
            if (str_ends_with($item, '.zip')) {
                $base = substr($item, 0, -4);
                if (LogicalBackup::isStamp($base) && is_file($root . DIRECTORY_SEPARATOR . $item)) {
                    $stamps[$base] = true;
                }
            }
        }
        $list = array_keys($stamps);
        rsort($list, SORT_STRING);

        return $list;
    }

    public function find(string $stamp): ?BackupEntry
    {
        if (!LogicalBackup::isStamp($stamp)) {
            return null;
        }

        $zip = $this->backup->zipPathFor($stamp);
        if ($zip !== null && is_file($zip)) {
            return $this->fromZip($stamp, $zip);
        }

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
            manifest: $this->manifestFromFile($dir . DIRECTORY_SEPARATOR . 'manifest.json'),
            isArchive: false,
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

    private function fromZip(string $stamp, string $zipPath): ?BackupEntry
    {
        if (!class_exists(ZipArchive::class)) {
            return new BackupEntry(
                stamp: $stamp,
                path: $zipPath,
                createdAt: $this->createdAt($stamp, $zipPath),
                bytes: (int) filesize($zipPath),
                hasDatabase: false,
                databaseBytes: 0,
                hasMedia: false,
                mediaFiles: 0,
                manifest: null,
                isArchive: true,
            );
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return null;
        }

        $sqlName = $this->zipEntryName($zip, ['database.sql', $stamp . '/database.sql']);
        $manifestName = $this->zipEntryName($zip, ['manifest.json', $stamp . '/manifest.json']);
        $hasMedia = $zip->locateName('media/') !== false
            || $zip->locateName($stamp . '/media/') !== false
            || $this->zipHasPrefix($zip, 'media/')
            || $this->zipHasPrefix($zip, $stamp . '/media/');

        $databaseBytes = 0;
        if ($sqlName !== null) {
            $stat = $zip->statName($sqlName);
            $databaseBytes = is_array($stat) ? (int) ($stat['size'] ?? 0) : 0;
        }

        $manifest = null;
        if ($manifestName !== null) {
            $raw = $zip->getFromName($manifestName);
            if (is_string($raw) && $raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $manifest = is_array($decoded) ? $decoded : null;
                } catch (Throwable) {
                    $manifest = null;
                }
            }
        }

        $mediaFiles = 0;
        if ($hasMedia) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || str_ends_with($name, '/')) {
                    continue;
                }
                if (str_starts_with($name, 'media/') || str_starts_with($name, $stamp . '/media/')) {
                    $mediaFiles++;
                }
            }
        }

        $zip->close();

        return new BackupEntry(
            stamp: $stamp,
            path: $zipPath,
            createdAt: $this->createdAt($stamp, $zipPath),
            bytes: (int) filesize($zipPath),
            hasDatabase: $sqlName !== null && $databaseBytes > 0,
            databaseBytes: $databaseBytes,
            hasMedia: $hasMedia,
            mediaFiles: $mediaFiles,
            manifest: $manifest,
            isArchive: true,
        );
    }

    /**
     * @param list<string> $candidates
     */
    private function zipEntryName(ZipArchive $zip, array $candidates): ?string
    {
        foreach ($candidates as $name) {
            if ($zip->locateName($name) !== false) {
                return $name;
            }
        }

        return null;
    }

    private function zipHasPrefix(ZipArchive $zip, string $prefix): bool
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function manifestFromFile(string $file): ?array
    {
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
    private function createdAt(string $stamp, string $path): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('Ymd-His', $stamp, new DateTimeZone('UTC'));
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
        $mtime = filemtime($path);

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
