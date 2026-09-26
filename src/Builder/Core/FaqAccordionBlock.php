<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class FaqAccordionBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'nexis/faq/accordion';
    }

    public function label(): string
    {
        return 'FAQ-Akkordeon';
    }

    public function allowsChildren(): bool
    {
        return true;
    }

    public function defaultProps(): array
    {
        return [
            'heading' => 'Häufige Fragen',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'heading' => (object) ['type' => 'string'],
            ],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $heading = (string) ($props['heading'] ?? '');
        $html = '<section class="nx-faq">';
        if ($heading !== '') {
            $html .= '<h2 class="nx-faq__heading">' . $this->e($heading) . '</h2>';
        }
        $html .= '<div class="nx-faq__list">' . $this->childrenHtml($block, $context) . '</div>';
        $html .= '</section>';

        return $html;
    }
}
