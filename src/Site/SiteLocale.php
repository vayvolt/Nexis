<?php

declare(strict_types=1);

namespace Nexis\Site;

final readonly class SiteLocale
{
    public function __construct(
        public string $locale,
        public string $label,
        public ?string $urlPrefix,
        public string $hreflang,
        public bool $isDefault,
        public bool $enabled,
    ) {
    }
}
