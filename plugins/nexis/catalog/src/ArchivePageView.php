<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

/**
 * Minimal page-like view model for theme templates on the catalog archive.
 */
final class ArchivePageView
{
    public function __construct(
        public string $title,
        public string $documentTitle,
        public ?string $metaDescription = null,
        public string $robots = 'index,follow',
    ) {
    }
}
