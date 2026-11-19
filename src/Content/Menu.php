<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\Site\SiteId;

final readonly class Menu
{
    public function __construct(
        public MenuId $id,
        public SiteId $siteId,
        public string $handle,
        public string $name,
        public string $locale,
    ) {
    }
}
