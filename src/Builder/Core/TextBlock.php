<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class TextBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/text';
    }

    public function label(): string
    {
        return 'Text';
    }

    public function defaultProps(): array
    {
        return ['text' => 'Fließtext hier…'];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'text' => (object) ['type' => 'string'],
            ],
            'required' => ['text'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $text = (string) ($props['text'] ?? '');

        return '<div class="bk-text"><p>' . nl2br($this->e($text)) . '</p></div>';
    }
}
