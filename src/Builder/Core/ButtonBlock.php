<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class ButtonBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/button';
    }

    public function label(): string
    {
        return 'Button';
    }

    public function defaultProps(): array
    {
        return ['label' => 'Call to Action', 'href' => '#', 'style' => 'primary'];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'label' => (object) ['type' => 'string', 'minLength' => 1],
                'href' => (object) ['type' => 'string', 'minLength' => 1],
                'style' => (object) ['type' => 'string', 'enum' => ['primary', 'secondary']],
            ],
            'required' => ['label', 'href', 'style'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $label = (string) ($props['label'] ?? '');
        $href = (string) ($props['href'] ?? '#');
        $style = (string) ($props['style'] ?? 'primary');

        return '<span class="bk-button"><a class="bk-btn bk-btn--' . $this->e($style) . '" href="' . $this->e($href) . '">'
            . $this->e($label) . '</a></span>';
    }
}
