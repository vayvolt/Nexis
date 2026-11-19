<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Cache\PageCache;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Server-rendered form builder: every button is a POST that re-saves the whole
 * definition, so no client state can drift from the stored fields.
 */
final class FormsBuilderController
{
    private const MAX_FIELDS = 40;

    public function __construct(
        private FormDefinitionStore $forms,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private AdminUi $ui,
        private PageCache $pageCache,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $this->forms->ensureDefaultContactForm($site->id);

        return $this->responses->html($this->views->render('admin.forms.builder_index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'forms' => $this->forms->all($site->id),
            'saved' => RequestInput::query($request, 'saved') === '1',
            'error' => RequestInput::query($request, 'error'),
        ], 'admin.layout'));
    }

    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $definition = $this->forms->find($site->id, (string) $request->getAttribute('id', ''));
        if ($definition === null) {
            return $this->responses->redirect(
                $basePath . '/admin/forms/builder?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_not_found')),
            );
        }

        return $this->responses->html($this->views->render('admin.forms.builder_edit', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'form' => $definition,
            'fieldTypes' => FormField::TYPES,
            'conditionOps' => FormField::OPS,
            'saved' => RequestInput::query($request, 'saved') === '1',
            'error' => RequestInput::query($request, 'error'),
        ], 'admin.layout'));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $name = RequestInput::string($request, 'name');
        if ($name === '') {
            return $this->responses->redirect(
                $basePath . '/admin/forms/builder?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_name_required')),
            );
        }
        $definition = $this->forms->create($site->id, $name, RequestInput::string($request, 'slug'));
        $this->pageCache->invalidateSite($site->id);

        return $this->responses->redirect($basePath . '/admin/forms/builder/' . rawurlencode($definition->id) . '?saved=1');
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $definition = $this->forms->find($site->id, (string) $request->getAttribute('id', ''));
        if ($definition === null) {
            return $this->responses->redirect(
                $basePath . '/admin/forms/builder?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_not_found')),
            );
        }
        $editUrl = $basePath . '/admin/forms/builder/' . rawurlencode($definition->id);

        $name = RequestInput::string($request, 'name');
        if ($name === '') {
            return $this->responses->redirect(
                $editUrl . '?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_name_required')),
            );
        }
        $slug = FormDefinition::normalizeSlug(RequestInput::string($request, 'slug'));
        if ($definition->slug === FormDefinition::CONTACT_SLUG) {
            $slug = FormDefinition::CONTACT_SLUG;
        } elseif ($slug === '') {
            $slug = $definition->slug;
        }
        if ($this->forms->slugTaken($site->id, $slug, $definition->id)) {
            return $this->responses->redirect(
                $editUrl . '?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_slug_taken')),
            );
        }

        $fields = $this->fieldsFromRequest($request);
        [$action, $index] = self::action(RequestInput::string($request, 'action', 'save'));
        $fields = match ($action) {
            'add' => self::appendField($fields, RequestInput::string($request, 'new_field_type')),
            'up' => self::move($fields, $index, -1),
            'down' => self::move($fields, $index, 1),
            'remove' => self::remove($fields, $index),
            default => $fields,
        };

        $definition->name = mb_substr($name, 0, 190);
        $definition->slug = $slug;
        $definition->fields = FormField::listFromArray(FormField::listToArray($fields));
        $definition->successMessage = self::nullable(RequestInput::string($request, 'success_message'), 500);
        $definition->submitLabel = self::nullable(RequestInput::string($request, 'submit_label'), 120);
        $this->forms->save($definition);
        $this->pageCache->invalidateSite($site->id);

        return $this->responses->redirect($editUrl . '?saved=1');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        try {
            if (!$this->forms->delete($site->id, (string) $request->getAttribute('id', ''))) {
                return $this->responses->redirect(
                    $basePath . '/admin/forms/builder?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_not_found')),
                );
            }
        } catch (\RuntimeException) {
            return $this->responses->redirect(
                $basePath . '/admin/forms/builder?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_cannot_delete_default')),
            );
        }
        $this->pageCache->invalidateSite($site->id);

        return $this->responses->redirect($basePath . '/admin/forms/builder?saved=1');
    }

    /**
     * @return list<FormField>
     */
    private function fieldsFromRequest(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        $rows = is_array($body) && is_array($body['fields'] ?? null) ? $body['fields'] : [];
        ksort($rows, SORT_NUMERIC);

        $raw = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $raw[] = [
                'key' => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'type' => (string) ($row['type'] ?? 'text'),
                'required' => (string) ($row['required'] ?? '') === '1',
                'placeholder' => (string) ($row['placeholder'] ?? ''),
                'options' => self::parseOptions((string) ($row['options'] ?? '')),
                'visibleWhen' => [
                    'field' => (string) ($row['cond_field'] ?? ''),
                    'op' => (string) ($row['cond_op'] ?? 'eq'),
                    'value' => (string) ($row['cond_value'] ?? ''),
                ],
            ];
        }

        return array_slice(FormField::listFromArray($raw), 0, self::MAX_FIELDS);
    }

    /**
     * One option per line, `value|Label` or just `value`.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function parseOptions(string $raw): array
    {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            $value = trim($parts[0]);
            $label = isset($parts[1]) ? trim($parts[1]) : $value;
            if ($value !== '') {
                $options[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
            }
        }

        return $options;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function action(string $raw): array
    {
        $parts = explode(':', $raw, 2);

        return [$parts[0], isset($parts[1]) ? (int) $parts[1] : -1];
    }

    /**
     * @param list<FormField> $fields
     * @return list<FormField>
     */
    private static function appendField(array $fields, string $type): array
    {
        if (count($fields) >= self::MAX_FIELDS) {
            return $fields;
        }
        if (!in_array($type, FormField::TYPES, true)) {
            $type = 'text';
        }
        $used = array_map(static fn (FormField $field): string => $field->key, $fields);
        $n = count($fields) + 1;
        while (in_array('field_' . $n, $used, true)) {
            $n++;
        }
        $fields[] = new FormField('field_' . $n, 'Feld ' . $n, $type, false);

        return $fields;
    }

    /**
     * @param list<FormField> $fields
     * @return list<FormField>
     */
    private static function move(array $fields, int $index, int $delta): array
    {
        $target = $index + $delta;
        if (!isset($fields[$index], $fields[$target])) {
            return $fields;
        }
        [$fields[$index], $fields[$target]] = [$fields[$target], $fields[$index]];

        return $fields;
    }

    /**
     * @param list<FormField> $fields
     * @return list<FormField>
     */
    private static function remove(array $fields, int $index): array
    {
        if (!isset($fields[$index])) {
            return $fields;
        }
        unset($fields[$index]);

        return array_values($fields);
    }

    private static function nullable(string $value, int $max): ?string
    {
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * @return array{User, Site, string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request): array|ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site'), 503);
        }
        if (!$this->policy->can($user, $site, 'forms.manage')) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'forms.manage']), 403);
        }

        return [$user, $site, $basePath];
    }
}
