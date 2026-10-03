<?php

declare(strict_types=1);

namespace Nexis\Infrastructure\Backup;

use DateTimeImmutable;

/**
 * One backup directory under storage/backups/{stamp}/ as shown in the admin UI.
 */
final readonly class BackupEntry
{
    /**
     * @param array<string, mixed>|null $manifest
     */
    public function __construct(
        public string $stamp,
        public string $path,
        public DateTimeImmutable $createdAt,
        public int $bytes,
        public bool $hasDatabase,
        public int $databaseBytes,
        public bool $hasMedia,
        public int $mediaFiles,
        public ?array $manifest,
    ) {
    }

    /**
     * A backup without a non-empty database.sql cannot be restored.
     */
    public function restorable(): bool
    {
        return $this->hasDatabase && $this->databaseBytes > 0;
    }

    /**
     * @return list<string>
     */
    public function parts(): array
    {
        $parts = [];
        if ($this->hasDatabase) {
            $parts[] = 'database.sql';
        }
        if ($this->hasMedia) {
            $parts[] = 'media/';
        }

        return $parts;
    }

    public function database(): ?string
    {
        $value = $this->manifest['database'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
