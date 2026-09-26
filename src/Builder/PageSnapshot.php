<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Auth\UserId;
use Nexis\Content\PageId;
use DateTimeImmutable;

final class PageSnapshot
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public private(set) SnapshotId $id,
        public private(set) PageId $pageId,
        public private(set) RevisionId $revisionId,
        public private(set) array $payload,
        public private(set) string $payloadHash,
        public private(set) ?UserId $publishedBy,
        public private(set) DateTimeImmutable $publishedAt,
    ) {
    }
}
