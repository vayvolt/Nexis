<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Content\PageId;
use Nexis\Auth\UserId;
use DateTimeImmutable;

final class PageRevision
{
    /**
     * @param array<string, mixed> $document
     */
    public function __construct(
        public private(set) RevisionId $id,
        public private(set) PageId $pageId,
        public private(set) int $schemaVersion,
        public private(set) array $document,
        public private(set) string $documentHash,
        public private(set) ?string $message,
        public private(set) ?UserId $createdBy,
        public private(set) DateTimeImmutable $createdAt,
    ) {
    }
}
