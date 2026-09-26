<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Site\SiteId;

final readonly class PublicNotFound
{
    public function __construct(
        public SiteId $siteId,
        public string $path,
        public string $locale,
        public string $method,
        public ?string $referer,
        public ?string $userAgent,
    ) {
    }
}
