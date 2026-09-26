<?php

declare(strict_types=1);

namespace Nexis\Search;

final readonly class SearchHit
{
    public function __construct(
        public string $pageId,
        public string $title,
        public string $path,
        public string $locale,
        public string $excerpt,
    ) {
    }
}
