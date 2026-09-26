<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginSettings;
use Nexis\Plugin\SettingsField;
use Nexis\Plugin\SettingsSchemaRegistry;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Generic admin UI for plugin settings declared via registerSettings().
 */
final class PluginSettingsAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private PluginCatalog $catalog,
        private SettingsSchemaRegistry $schema,
        private PdoSiteSettingsRepository $store,
        private AdminUi $ui,
    ) {
    }

    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $pluginId = trim(RequestInput::query($request, 'plugin', ''));
        if ($pluginId === '' || !$this->schema->hasSchema($pluginId)) {
            return $this->responses->redirect($basePath . '/admin/plugins');
        }
        if (!in_array($pluginId, $this->catalog->enabledKeys($site->id), true)) {
            return $this->responses->redirect($basePath . '/admin/plugins?error=' . rawurlencode(
                $this->ui->get($user, 'admin.plugins.settings.disabled'),
            ));
        }

        $bag = new PluginSettings($pluginId, $this->schema, $this->store);
        $fields = [];
        foreach ($this->schema->fieldsFor($pluginId) as $name => $field) {
            $fields[] = [
                'name' => $name,
                'type' => $field->type,
                'label' => $this->fieldLabel($user, $pluginId, $field),
                'value' => $bag->get($site->id, $name, $field->default),
                'secret' => $field->isSecret(),
            ];
        }

        return $this->responses->html($this->views->render('admin.plugins.settings', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'pluginId' => $pluginId,
            'pluginName' => $this->pluginName($site->id, $pluginId),
            'fields' => $fields,
            'notice' => RequestInput::query($request, 'saved') === '1'
                ? $this->ui->get($user, 'admin.common.saved')
                : '',
            'error' => RequestInput::query($request, 'error'),
        ], 'admin.layout'));
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $pluginId = trim(RequestInput::string($request, 'plugin'));
        if ($pluginId === '' || !$this->schema->hasSchema($pluginId)) {
            return $this->responses->redirect($basePath . '/admin/plugins');
        }
        if (!in_array($pluginId, $this->catalog->enabledKeys($site->id), true)) {
            return $this->responses->redirect($basePath . '/admin/plugins');
        }

        $bag = new PluginSettings($pluginId, $this->schema, $this->store);
        foreach ($this->schema->fieldsFor($pluginId) as $name => $field) {
            $raw = RequestInput::string($request, 'field_' . $name);
            $value = $this->castValue($field, $raw, $request, $name);
            if ($field->type === 'email' && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                return $this->responses->redirect(
                    $basePath . '/admin/plugins/settings?plugin=' . rawurlencode($pluginId)
                    . '&error=' . rawurlencode($this->ui->get($user, 'admin.plugins.settings.invalid_email', ['field' => $name])),
                );
            }
            if ($field->type === 'url' && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_URL)) {
                return $this->responses->redirect(
                    $basePath . '/admin/plugins/settings?plugin=' . rawurlencode($pluginId)
                    . '&error=' . rawurlencode($this->ui->get($user, 'admin.plugins.settings.invalid_url', ['field' => $name])),
                );
            }
            if ($field->isSecret() && $value === '') {
                continue; // keep existing secret when blank
            }
            $bag->set($site->id, $name, $value);
        }

        return $this->responses->redirect(
            $basePath . '/admin/plugins/settings?plugin=' . rawurlencode($pluginId) . '&saved=1',
        );
    }

    /**
     * @return array{User, \Nexis\Site\Site, string}|ResponseInterface
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
        if (!$this->policy->can($user, $site, Permission::PLUGIN_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'plugin.manage']), 403);
        }

        return [$user, $site, $basePath];
    }

    private function fieldLabel(User $user, string $pluginId, SettingsField $field): string
    {
        if ($field->label !== '') {
            $translated = $this->ui->get($user, $field->label);
            if ($translated !== $field->label) {
                return $translated;
            }
            if (!str_contains($field->label, '.')) {
                return $field->label;
            }
        }

        return $field->name;
    }

    private function pluginName(\Nexis\Site\SiteId $siteId, string $pluginId): string
    {
        foreach ($this->catalog->listForSite($siteId) as $row) {
            if (($row['key'] ?? '') === $pluginId) {
                return (string) ($row['name'] ?? $pluginId);
            }
        }

        return $pluginId;
    }

    private function castValue(SettingsField $field, string $raw, ServerRequestInterface $request, string $name): mixed
    {
        return match ($field->type) {
            'bool' => RequestInput::string($request, 'field_' . $name) === '1',
            'int' => (int) $raw,
            default => $raw,
        };
    }
}
