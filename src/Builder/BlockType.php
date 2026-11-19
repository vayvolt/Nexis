<?php

declare(strict_types=1);

namespace Nexis\Builder;

interface BlockType
{
    public function type(): string;

    public function label(): string;

    public function allowsChildren(): bool;

    /**
     * @return object|array<string, mixed>
     */
    public function propsSchema(): object|array;

    /**
     * @return array<string, mixed>
     */
    public function defaultProps(): array;

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    public function render(array $block, array $context): string;
}
