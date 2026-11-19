<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Site\SiteId;

/**
 * Extra public paths (archives etc.) to include in locale sitemaps.
 */
interface SitemapArchiveProvider
{
    /**
     * @return list<string> absolute site paths, e.g. `/blog`
     */
    public function pathsFor(SiteId $siteId): array;
}
