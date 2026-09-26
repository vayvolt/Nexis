<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class SectionBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/section';
    }

    public function label(): string
    {
        return 'Abschnitt';
    }

    public function allowsChildren(): bool
    {
        return true;
    }

    public function defaultProps(): array
    {
        return ['width' => 'wide', 'padding' => 'lg'];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'width' => (object) ['type' => 'string', 'enum' => ['narrow', 'wide', 'full']],
                'padding' => (object) ['type' => 'string', 'enum' => ['sm', 'md', 'lg', 'xl']],
            ],
            'required' => ['width', 'padding'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $width = (string) ($props['width'] ?? 'wide');
        $padding = (string) ($props['padding'] ?? 'lg');

        return '<section class="bk-section bk-section--' . $this->e($width) . ' bk-pad--' . $this->e($padding) . '">'
            . $this->childrenHtml($block, $context)
            . '</section>';
    }
}
