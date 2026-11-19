<?php

declare(strict_types=1);

namespace Nexis\Plugin;

/**
 * @param callable(array<string, mixed>): string|callable(): string $renderer
 */
final class AdminSlot
{
    public function __construct(
        public private(set) string $name,
        public private(set) string $pluginId,
        public private(set) mixed $renderer,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(array $context = []): string
    {
        $renderer = $this->renderer;
        if (is_callable($renderer)) {
            return (string) $renderer($context);
        }

        return '';
    }
}
