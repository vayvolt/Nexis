<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Auth\UserId;
use Nexis\Builder\SnapshotId;
use Nexis\Content\PageId;
use Nexis\Site\SiteId;

final readonly class PagePublished
{
    public function __construct(
        public SiteId $siteId,
        public PageId $pageId,
        public SnapshotId $snapshotId,
        public string $locale,
        public string $path,
        public string $title,
        public UserId $actorId,
    ) {
    }
}
