<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\AuthSettings;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Cache\PageCache;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\MaintenanceMode;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SettingsAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PdoSiteSettingsRepository $settings,
        private AuthSettings $authSettings,
        private SitePolicy $policy,
        private PageCache $cache,
        private AdminUi $ui,
        private MaintenanceMode $maintenance,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        return $this->responses->html($this->views->render('admin.settings.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => RequestInput::query($request, 'error'),
            'saved' => RequestInput::query($request, 'saved') === '1',
            'detectOnRoot' => (bool) $this->settings->get($site->id, 'i18n.detect_on_root', false),
            'missingPolicy' => (string) $this->settings->get($site->id, 'i18n.missing_policy', 'fallback'),
            'switcherUnpublished' => (string) $this->settings->get($site->id, 'i18n.switcher_unpublished', 'hide'),
            'localeDomains' => $this->sites->localeDomains($site->id),
            'registrationEnabled' => $this->authSettings->registrationEnabled($site->id),
            'passwordResetEnabled' => $this->authSettings->passwordResetEnabled($site->id),
            'maintenanceEnabled' => $this->maintenance->isEnabled($site->id),
            'maintenanceMessage' => $this->maintenance->message($site->id),
            'canManage' => $this->policy->can($user, $site, Permission::SETTINGS_MANAGE),
        ], 'admin.layout'));
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $name = trim(RequestInput::string($request, 'name'));
        $domain = trim(RequestInput::string($request, 'primary_domain'));
        $defaultLocale = RequestInput::string($request, 'default_locale', $site->defaultLocale);
        $strategyRaw = RequestInput::string($request, 'locale_url_strategy', $site->localeUrlStrategy->value);
        $strategy = LocaleUrlStrategy::tryFrom($strategyRaw) ?? $site->localeUrlStrategy;
        if ($name === '' || $domain === '') {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_name_domain')));
        }
        if ($site->locale($defaultLocale) === null) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_default_enabled')));
        }

        $this->sites->updateBasics($site->id, $name, $domain, $defaultLocale, $strategy);
        $this->settings->set($site->id, 'i18n.detect_on_root', RequestInput::string($request, 'detect_on_root') === '1');
        $missing = RequestInput::string($request, 'missing_policy', 'fallback');
        if (!in_array($missing, ['fallback', '404'], true)) {
            $missing = 'fallback';
        }
        $this->settings->set($site->id, 'i18n.missing_policy', $missing);
        $switcher = RequestInput::string($request, 'switcher_unpublished', 'hide');
        if (!in_array($switcher, ['hide', 'label'], true)) {
            $switcher = 'hide';
        }
        $this->settings->set($site->id, 'i18n.switcher_unpublished', $switcher);

        $this->authSettings->setRegistrationEnabled(
            $site->id,
            RequestInput::string($request, 'registration_enabled') === '1',
        );
        $this->authSettings->setPasswordResetEnabled(
            $site->id,
            RequestInput::string($request, 'password_reset_enabled') === '1',
        );

        $this->maintenance->setEnabled(
            $site->id,
            RequestInput::string($request, 'maintenance_enabled') === '1',
        );
        $this->maintenance->setMessage(
            $site->id,
            RequestInput::string($request, 'maintenance_message'),
        );

        if ($strategy === LocaleUrlStrategy::Domain) {
            $map = [];
            $body = $request->getParsedBody();
            $hosts = is_array($body) ? ($body['locale_host'] ?? []) : [];
            if (is_array($hosts)) {
                foreach ($hosts as $locale => $host) {
                    if (!is_string($locale) || !is_string($host)) {
                        continue;
                    }
                    $host = strtolower(trim($host));
                    if ($host !== '') {
                        $map[strtolower($locale)] = $host;
                    }
                }
            }
            $this->sites->replaceLocaleDomains($site->id, $map);
        }

        $this->cache->invalidateSite($site->id);

        $tab = RequestInput::string($request, 'return_tab', 'website');
        if (!in_array($tab, ['website', 'i18n', 'members', 'operations'], true)) {
            $tab = 'website';
        }

        return $this->responses->redirect($basePath . '/admin/settings?saved=1#' . $tab);
    }

    public function addLocale(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $locale = strtolower(trim(RequestInput::string($request, 'locale')));
        $label = trim(RequestInput::string($request, 'label'));
        $prefix = trim(RequestInput::string($request, 'url_prefix'));
        $hreflang = trim(RequestInput::string($request, 'hreflang'));
        if (!$this->isValidLocaleCode($locale)) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_invalid')));
        }
        if ($label === '') {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_label_required')));
        }
        foreach ($site->locales as $existing) {
            if ($existing->locale === $locale) {
                return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_exists')));
            }
        }
        if ($prefix === '') {
            $prefix = explode('-', $locale)[0];
        }
        if ($hreflang === '') {
            $hreflang = $locale;
        }

        try {
            $this->sites->addLocale($site->id, $locale, $label, $prefix, $hreflang, true);
        } catch (\Throwable) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_create_failed')));
        }
        $this->cache->invalidateSite($site->id);

        return $this->responses->redirect($basePath . '/admin/settings?saved=1#locales');
    }

    public function updateLocale(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $locale = strtolower(trim(RequestInput::string($request, 'locale')));
        $label = trim(RequestInput::string($request, 'label'));
        $prefix = trim(RequestInput::string($request, 'url_prefix'));
        $hreflang = trim(RequestInput::string($request, 'hreflang'));
        $enabled = RequestInput::string($request, 'enabled') === '1';
        $match = array_find($site->locales, static fn ($loc): bool => $loc->locale === $locale);
        if ($match === null) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_not_found')));
        }
        if ($label === '') {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_label_required')));
        }
        if (!$enabled && ($match->isDefault || $locale === $site->defaultLocale)) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_default_disable')));
        }
        if ($prefix === '') {
            $prefix = explode('-', $locale)[0];
        }
        if ($hreflang === '') {
            $hreflang = $locale;
        }

        $this->sites->updateLocale($site->id, $locale, $label, $prefix, $hreflang, $enabled);
        $this->cache->invalidateSite($site->id);

        return $this->responses->redirect($basePath . '/admin/settings?saved=1#locales');
    }

    public function deleteLocale(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $locale = strtolower(trim(RequestInput::string($request, 'locale')));
        $match = array_find($site->locales, static fn ($loc): bool => $loc->locale === $locale);
        if ($match === null) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_not_found')));
        }
        if ($match->isDefault || $locale === $site->defaultLocale) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_default_delete')));
        }
        if ($this->sites->countPagesForLocale($site->id, $locale) > 0) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_has_pages')));
        }
        if (!$this->sites->deleteLocale($site->id, $locale)) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_delete_failed')));
        }
        $this->cache->invalidateSite($site->id);

        return $this->responses->redirect($basePath . '/admin/settings?saved=1#locales');
    }

    public function setDefaultLocale(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request, true);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $locale = strtolower(trim(RequestInput::string($request, 'locale')));
        $match = array_find($site->locales, static fn ($loc): bool => $loc->locale === $locale);
        if ($match === null) {
            return $this->responses->redirect($basePath . '/admin/settings?error=' . rawurlencode($this->ui->get($user, 'admin.error.settings_locale_not_found')) . '#locales');
        }
        if ($locale === $site->defaultLocale) {
            return $this->responses->redirect($basePath . '/admin/settings?saved=1#locales');
        }

        $this->sites->updateBasics(
            $site->id,
            $site->name,
            $site->primaryDomain,
            $locale,
            $site->localeUrlStrategy,
        );
        $this->cache->invalidateSite($site->id);

        return $this->responses->redirect($basePath . '/admin/settings?saved=1#locales');
    }

    /**
     * @return array{User, \Nexis\Site\Site, string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request, bool $requireManage = false): array|ResponseInterface
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
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }
        if ($requireManage && !$this->policy->can($user, $site, Permission::SETTINGS_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'settings.manage']), 403);
        }

        return [$user, $site, $basePath];
    }

    private function isValidLocaleCode(string $locale): bool
    {
        return (bool) preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/', $locale);
    }
}
