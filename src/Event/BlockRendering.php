<?php

declare(strict_types=1);

namespace Nexis\Event;

/**
 * Dispatched before each block render. Listeners may enrich block props or context.
 *
 * @param array<string, mixed> $block
 * @param array<string, mixed> $context
 */
final class BlockRendering
{
    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    public function __construct(
        public array $block,
        public array $context,
    ) {
    }
}
