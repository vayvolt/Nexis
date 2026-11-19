<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Content\PageId;

interface SnapshotRepository
{
    public function findById(SnapshotId $id): ?PageSnapshot;

    public function save(PageSnapshot $snapshot): void;
}
