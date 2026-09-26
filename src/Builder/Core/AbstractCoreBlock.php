<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

use Nexis\Builder\BlockType;

abstract class AbstractCoreBlock implements BlockType
{
    public function allowsChildren(): bool
    {
        return false;
    }

    public function defaultProps(): array
    {
        return [];
    }

    /**
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    protected function props(array $block): array
    {
        $props = $block['props'] ?? [];

        return is_array($props) ? $props : [];
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    protected function childrenHtml(array $block, array $context): string
    {
        $children = $block['children'] ?? [];
        if (!is_array($children)) {
            return '';
        }
        $renderer = $context['renderer'] ?? null;
        if (!is_callable($renderer)) {
            return '';
        }
        $html = '';
        foreach ($children as $child) {
            if (is_array($child)) {
                $html .= $renderer($child, $context);
            }
        }

        return $html;
    }

    protected function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
