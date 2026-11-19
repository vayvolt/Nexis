<?php

declare(strict_types=1);

namespace Nexis\Event;

/**
 * Dispatched before a page document is rendered. Listeners may enrich document/context.
 *
 * @param array<string, mixed> $document
 * @param array<string, mixed> $context
 */
final class PageRendering
{
    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $context
     */
    public function __construct(
        public array $document,
        public array $context,
    ) {
    }
}
