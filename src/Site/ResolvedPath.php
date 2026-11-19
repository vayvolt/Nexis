<?php

declare(strict_types=1);

namespace Nexis\Site;

final readonly class ResolvedPath
{
    public function __construct(
        public string $locale,
        public string $pagePath,
    ) {
    }
}
