<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Site\SiteId;

interface PatternRepository
{
    public function findById(PatternId $id, SiteId $siteId): ?BlockPattern;

    /**
     * @return list<BlockPattern>
     */
    public function listBySite(SiteId $siteId): array;

    public function save(BlockPattern $pattern): void;

    public function delete(PatternId $id, SiteId $siteId): bool;
}
