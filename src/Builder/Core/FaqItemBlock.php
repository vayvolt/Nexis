<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class FaqItemBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'nexis/faq/item';
    }

    public function label(): string
    {
        return 'FAQ-Eintrag';
    }

    public function defaultProps(): array
    {
        return [
            'question' => 'Frage?',
            'answer' => 'Antwort.',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'question' => (object) ['type' => 'string', 'minLength' => 1],
                'answer' => (object) ['type' => 'string'],
            ],
            'required' => ['question'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $question = (string) ($props['question'] ?? 'Frage?');
        $answer = (string) ($props['answer'] ?? '');
        $html = '<details class="nx-faq__item">';
        $html .= '<summary class="nx-faq__question">' . $this->e($question) . '</summary>';
        $html .= '<div class="nx-faq__answer">' . nl2br($this->e($answer)) . '</div>';
        $html .= '</details>';

        return $html;
    }
}
