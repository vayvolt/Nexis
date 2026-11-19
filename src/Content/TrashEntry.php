<?php

declare(strict_types=1);

namespace Nexis\Content;

use DateTimeImmutable;

/**
 * Soft-deleted page row with path/slug remembered for restore.
 */
final class TrashEntry
{
    public function __construct(
        public Page $page,
        public DateTimeImmutable $deletedAt,
        public ?string $restorePath,
        public ?string $restoreSlug,
    ) {
    }
}
