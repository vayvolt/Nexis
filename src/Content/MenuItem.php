<?php

declare(strict_types=1);

namespace Nexis\Content;

final readonly class MenuItem
{
    /**
     * @param list<MenuItem> $children
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?PageId $pageId,
        public ?string $url,
        public int $sortOrder,
        public ?string $parentId = null,
        public array $children = [],
    ) {
    }
}
