<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Site\SiteId;

final class BlockPattern
{
    /**
     * @param array<string, mixed> $document Single block subtree (node with id/type/props/children).
     */
    public function __construct(
        public private(set) PatternId $id,
        public private(set) SiteId $siteId,
        public private(set) string $name,
        public private(set) ?string $description,
        public private(set) array $document,
        public private(set) ?string $createdBy,
        public private(set) \DateTimeImmutable $createdAt,
        public private(set) \DateTimeImmutable $updatedAt,
    ) {
    }
}
