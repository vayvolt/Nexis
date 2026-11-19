<?php

declare(strict_types=1);

namespace Nexis\Site;

/**
 * Stats from a content-package import (or dry-run).
 */
final readonly class SiteImportResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public bool $dryRun,
        public int $pages,
        public int $pagesUpdated,
        public int $media,
        public int $mediaUpdated,
        public int $mediaSkipped,
        public int $published,
        public int $menus,
        public int $redirects,
        public int $settings,
        public array $warnings = [],
    ) {
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   pages: int,
     *   pages_updated: int,
     *   media: int,
     *   media_updated: int,
     *   media_skipped: int,
     *   published: int,
     *   menus: int,
     *   redirects: int,
     *   settings: int,
     *   warnings: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'pages' => $this->pages,
            'pages_updated' => $this->pagesUpdated,
            'media' => $this->media,
            'media_updated' => $this->mediaUpdated,
            'media_skipped' => $this->mediaSkipped,
            'published' => $this->published,
            'menus' => $this->menus,
            'redirects' => $this->redirects,
            'settings' => $this->settings,
            'warnings' => $this->warnings,
        ];
    }
}
