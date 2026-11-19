<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class HeadingBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/heading';
    }

    public function label(): string
    {
        return 'Überschrift';
    }

    public function defaultProps(): array
    {
        return ['text' => 'Neue Überschrift', 'level' => 2];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'text' => (object) ['type' => 'string', 'minLength' => 1],
                'level' => (object) ['type' => 'integer', 'minimum' => 1, 'maximum' => 6],
            ],
            'required' => ['text', 'level'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $level = max(1, min(6, (int) ($props['level'] ?? 2)));
        $text = (string) ($props['text'] ?? '');

        return '<h' . $level . ' class="bk-heading">' . $this->e($text) . '</h' . $level . '>';
    }
}
