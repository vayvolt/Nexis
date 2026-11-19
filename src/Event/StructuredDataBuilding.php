<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Content\Page;
use Nexis\Site\Site;

/**
 * Dispatched while building public JSON-LD. Plugins may append @graph nodes
 * (e.g. Product/Offer from nexis/catalog or a future shop plugin).
 */
final class StructuredDataBuilding
{
    /**
     * @param array<string, mixed> $document
     * @param list<array<string, mixed>> $graph
     */
    public function __construct(
        public readonly Page $page,
        public readonly Site $site,
        public readonly string $canonicalUrl,
        public readonly array $document,
        public readonly string $webpageId,
        public readonly string $basePath,
        public readonly ?string $logoUrl,
        public array $graph,
    ) {
    }

    /**
     * @param array<string, mixed> $node
     */
    public function append(array $node): void
    {
        $this->graph[] = $node;
    }
}
