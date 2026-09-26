<?php

declare(strict_types=1);

namespace Nexis\Search;

use Nexis\Site\SiteId;

interface SearchPort
{
    /**
     * @return list<SearchHit>
     */
    public function search(SiteId $siteId, string $locale, string $query, int $limit = 20): array;
}
