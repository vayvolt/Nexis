<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Auth\UserId;
use Nexis\Content\PageId;
use Nexis\Site\SiteId;

final readonly class PageReviewRejected
{
    public function __construct(
        public SiteId $siteId,
        public PageId $pageId,
        public string $locale,
        public string $path,
        public string $title,
        public UserId $actorId,
    ) {
    }
}
