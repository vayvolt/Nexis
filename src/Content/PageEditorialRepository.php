<?php

declare(strict_types=1);

namespace Nexis\Content;

interface PageEditorialRepository
{
    /**
     * @return list<PageEditorialItem>
     */
    public function listForPage(PageId $pageId, int $limit = 50): array;

    public function findById(PageEditorialItemId $id, PageId $pageId): ?PageEditorialItem;

    public function save(PageEditorialItem $item): void;

    public function delete(PageEditorialItemId $id, PageId $pageId): bool;
}
