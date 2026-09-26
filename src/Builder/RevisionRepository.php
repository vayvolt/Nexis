<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Content\PageId;

interface RevisionRepository
{
    public function findById(RevisionId $id): ?PageRevision;

    public function latestForPage(PageId $pageId): ?PageRevision;

    /**
     * @return list<PageRevision>
     */
    public function listForPage(PageId $pageId, int $limit = 50): array;

    public function save(PageRevision $revision): void;
}
