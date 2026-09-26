<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Builder\Core\AbstractCoreBlock;
use Nexis\Cache\DynamicPageTokens;
use Nexis\I18n\PublicUi;

final class ContactFormBlock extends AbstractCoreBlock
{
    public function __construct(
        private PublicUi $ui,
    ) {
    }

    public function type(): string
    {
        return 'nexis/forms/contact';
    }

    public function label(): string
    {
        return 'Kontaktformular';
    }

    public function defaultProps(): array
    {
        return [
            'heading' => 'Kontakt',
            'submitLabel' => 'Senden',
            'successMessage' => 'Vielen Dank!',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'heading' => (object) ['type' => 'string'],
                'submitLabel' => (object) ['type' => 'string', 'minLength' => 1],
                'successMessage' => (object) ['type' => 'string'],
            ],
            'required' => ['submitLabel'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $locale = (string) ($context['locale'] ?? 'de');
        $heading = (string) ($props['heading'] ?? '');
        if ($heading === '' || $heading === 'Kontakt') {
            $heading = $this->ui->get($locale, 'public.forms.heading_default');
        }
        $submit = (string) ($props['submitLabel'] ?? '');
        if ($submit === '' || $submit === 'Senden') {
            $submit = $this->ui->get($locale, 'public.forms.submit_default');
        }
        $basePath = (string) ($context['basePath'] ?? '');
        $csrf = !empty($context['cacheSafe'])
            ? DynamicPageTokens::CSRF
            : (string) ($context['csrf'] ?? '');
        $idempotency = !empty($context['cacheSafe'])
            ? DynamicPageTokens::IDEMPOTENCY
            : $this->idempotencyKey();
        $flash = '';
        if (!empty($context['formOk'])) {
            $success = (string) ($props['successMessage'] ?? '');
            $flash = ($success === '' || $success === 'Vielen Dank!')
                ? $this->ui->get($locale, 'public.forms.success_default')
                : $success;
        } elseif (isset($context['formFlash']) && is_string($context['formFlash']) && $context['formFlash'] !== '') {
            $flash = $context['formFlash'];
        }

        $html = '<div class="bk-form bk-form-contact">';
        if ($heading !== '') {
            $html .= '<h2>' . $this->e($heading) . '</h2>';
        }
        if ($flash !== '') {
            $html .= '<p class="bk-form-flash">' . $this->e($flash) . '</p>';
        }
        $html .= '<form method="post" action="' . $this->e($basePath) . '/ext/nexis/forms/submit">';
        $html .= '<input type="hidden" name="_csrf" value="' . $this->e($csrf) . '">';
        $html .= '<input type="hidden" name="locale" value="' . $this->e($locale) . '">';
        $html .= '<input type="hidden" name="_idempotency_key" value="' . $this->e($idempotency) . '">';
        $html .= '<label>' . $this->e($this->ui->get($locale, 'public.forms.name'))
            . '<input name="name" required></label>';
        $html .= '<label>' . $this->e($this->ui->get($locale, 'public.forms.email'))
            . '<input type="email" name="email" required></label>';
        $html .= '<label>' . $this->e($this->ui->get($locale, 'public.forms.message'))
            . '<textarea name="message" rows="4" required></textarea></label>';
        $html .= '<p><button type="submit">' . $this->e($submit) . '</button></p>';
        $html .= '</form></div>';

        return $html;
    }

    private function idempotencyKey(): string
    {
        try {
            return \Nexis\Support\Uuid::v7();
        } catch (\Throwable) {
            return bin2hex(random_bytes(16));
        }
    }
}
