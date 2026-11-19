<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class ColumnsBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/columns';
    }

    public function label(): string
    {
        return 'Spalten';
    }

    public function allowsChildren(): bool
    {
        return true;
    }

    public function defaultProps(): array
    {
        return ['columns' => 2];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'columns' => (object) ['type' => 'integer', 'minimum' => 2, 'maximum' => 4],
            ],
            'required' => ['columns'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $columns = max(2, min(4, (int) ($props['columns'] ?? 2)));
        $children = $block['children'] ?? [];
        if (!is_array($children)) {
            $children = [];
        }
        $renderer = $context['renderer'] ?? null;
        $html = '<div class="bk-columns bk-columns--' . $columns . '">';
        foreach ($children as $child) {
            if (!is_array($child) || !is_callable($renderer)) {
                continue;
            }
            $html .= '<div class="bk-column">' . $renderer($child, $context) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }
}
