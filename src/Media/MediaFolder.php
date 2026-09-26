<?php

declare(strict_types=1);

namespace Nexis\Media;

use Nexis\Site\SiteId;

final class MediaFolder
{
    public function __construct(
        public private(set) MediaFolderId $id,
        public private(set) SiteId $siteId,
        public private(set) string $name,
        public private(set) int $sortOrder,
        public private(set) \DateTimeImmutable $createdAt,
    ) {
    }
}
