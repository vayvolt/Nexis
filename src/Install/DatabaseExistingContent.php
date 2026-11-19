<?php

declare(strict_types=1);

namespace Nexis\Install;

/**
 * Snapshot of whether the target database already holds Nexis (or other) tables.
 */
final class DatabaseExistingContent
{
    public function __construct(
        public readonly int $tableCount,
        public readonly int $siteCount,
        public readonly int $migrationCount,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->tableCount === 0;
    }

    public function looksLikeNexis(): bool
    {
        return $this->siteCount > 0 || $this->migrationCount > 0;
    }
}
