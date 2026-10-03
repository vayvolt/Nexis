<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Builder\Core\AbstractCoreBlock;
use Nexis\Cache\DynamicPageTokens;
use Nexis\I18n\PublicUi;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;

final class ContactFormBlock extends AbstractCoreBlock
{
    public function __construct(
        private PublicUi $ui,
        private FormDefinitionStore $forms,
        private SiteRepository $sites,
    ) {
    }

    public function type(): string
    {
        return 'nexis/forms/contact';
    }

    public function label(): string
    {
        return 'Formular';
    }

    public function defaultProps(): array
    {
        return [
            'heading' => 'Kontakt',
            'submitLabel' => 'Senden',
            'successMessage' => 'Vielen Dank!',
            'formId' => '',
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
                'formId' => (object) ['type' => 'string'],
            ],
            'required' => ['submitLabel'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $locale = (string) ($context['locale'] ?? 'de');
        $site = $context['site'] ?? $this->sites->installed();
        $definition = FormDefinition::defaultContact();
        if ($site instanceof Site) {
            $formId = trim((string) ($props['formId'] ?? ''));
            if ($formId === '') {
                $formId = FormDefinition::CONTACT_SLUG;
            }
            $definition = $this->forms->find($site->id, $formId)
                ?? $this->forms->find($site->id, FormDefinition::CONTACT_SLUG)
                ?? FormDefinition::defaultContact($site->id->value);
        }

        $heading = (string) ($props['heading'] ?? '');
        if ($heading === '' || $heading === 'Kontakt') {
            $heading = $definition->name !== ''
                ? $definition->name
                : $this->ui->get($locale, 'public.forms.heading_default');
        }
        $submit = (string) ($props['submitLabel'] ?? '');
        if ($submit === '' || $submit === 'Senden') {
            $fromDef = trim((string) ($definition->submitLabel ?? ''));
            $submit = $fromDef !== '' ? $fromDef : $this->ui->get($locale, 'public.forms.submit_default');
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
            if ($success === '' || $success === 'Vielen Dank!') {
                $fromDef = trim((string) ($definition->successMessage ?? ''));
                $flash = $fromDef !== '' ? $fromDef : $this->ui->get($locale, 'public.forms.success_default');
            } else {
                $flash = $success;
            }
        } elseif (isset($context['formFlash']) && is_string($context['formFlash']) && $context['formFlash'] !== '') {
            $flash = $context['formFlash'];
        }

        $html = '<div class="bk-form bk-form-contact nx-form">';
        if ($heading !== '') {
            $html .= '<h2>' . $this->e($heading) . '</h2>';
        }
        if ($flash !== '') {
            $html .= '<p class="bk-form-flash">' . $this->e($flash) . '</p>';
        }
        $html .= '<form method="post" action="' . $this->e($basePath) . '/ext/nexis/forms/submit" data-nx-form>';
        $html .= '<input type="hidden" name="_csrf" value="' . $this->e($csrf) . '">';
        $html .= '<input type="hidden" name="locale" value="' . $this->e($locale) . '">';
        $html .= '<input type="hidden" name="_idempotency_key" value="' . $this->e($idempotency) . '">';
        $html .= '<input type="hidden" name="form_slug" value="' . $this->e($definition->slug) . '">';
        if ($definition->id !== '') {
            $html .= '<input type="hidden" name="form_id" value="' . $this->e($definition->id) . '">';
        }
        foreach ($definition->fields as $field) {
            $html .= $this->renderField($field, $locale);
        }
        $html .= '<p><button type="submit">' . $this->e($submit) . '</button></p>';
        $html .= '</form></div>';

        return $html;
    }

    private function renderField(FormField $field, string $locale): string
    {
        $label = $field->label;
        if ($field->key === 'name' && ($label === 'Name' || $label === '')) {
            $label = $this->ui->get($locale, 'public.forms.name');
        } elseif ($field->key === 'email' && in_array($label, ['E-Mail', 'Email', ''], true)) {
            $label = $this->ui->get($locale, 'public.forms.email');
        } elseif ($field->key === 'message' && in_array($label, ['Nachricht', 'Message', ''], true)) {
            $label = $this->ui->get($locale, 'public.forms.message');
        }

        $visibleAttr = '';
        if ($field->visibleWhen !== null) {
            $visibleAttr = ' data-visible-when="' . $this->e((string) json_encode($field->visibleWhen, JSON_UNESCAPED_UNICODE)) . '"';
        }

        $html = '<div class="nx-form-field" data-nx-form-field data-key="' . $this->e($field->key) . '"'
            . ' data-required="' . ($field->required ? '1' : '0') . '"' . $visibleAttr . '>';

        if ($field->type === 'checkbox') {
            $html .= '<label><input type="checkbox" name="' . $this->e($field->key) . '" value="1"'
                . ($field->required ? ' required' : '') . '> ' . $this->e($label) . '</label>';
            $html .= '</div>';

            return $html;
        }

        $html .= '<label>' . $this->e($label);
        $req = $field->required ? ' required' : '';
        $ph = $field->placeholder !== '' ? ' placeholder="' . $this->e($field->placeholder) . '"' : '';
        if ($field->type === 'textarea') {
            $html .= '<textarea name="' . $this->e($field->key) . '" rows="4"' . $req . $ph . '></textarea>';
        } elseif ($field->type === 'select') {
            $html .= '<select name="' . $this->e($field->key) . '"' . $req . '>';
            $html .= '<option value="">—</option>';
            foreach ($field->options as $opt) {
                $html .= '<option value="' . $this->e($opt['value']) . '">' . $this->e($opt['label']) . '</option>';
            }
            $html .= '</select>';
        } elseif ($field->type === 'email') {
            $html .= '<input type="email" name="' . $this->e($field->key) . '"' . $req . $ph . '>';
        } elseif ($field->type === 'number') {
            $html .= '<input type="number" name="' . $this->e($field->key) . '"' . $req . $ph . '>';
        } else {
            $html .= '<input type="text" name="' . $this->e($field->key) . '"' . $req . $ph . '>';
        }
        $html .= '</label></div>';

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
