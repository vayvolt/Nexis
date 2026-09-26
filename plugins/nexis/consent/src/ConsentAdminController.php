<?php

declare(strict_types=1);

namespace Nexis\Plugins\Consent;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ConsentAdminController
{
    private const LOCALE_FIELDS = [
        'text',
        'accept_label',
        'reject_label',
        'save_label',
        'privacy_label',
        'settings_label',
        'analytics_label',
        'marketing_label',
    ];

    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private PdoSiteSettingsRepository $settings,
        private AdminUi $ui,
        private ConsentBanner $banner,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $trackingConfigured = $this->banner->trackingConfigured($site);

        return $this->responses->html($this->views->render('admin.consent.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'enabled' => (bool) $this->settings->get($site->id, 'plugin:nexis/consent.enabled', true),
            'trackingConfigured' => $trackingConfigured,
            'privacyUrl' => (string) $this->settings->get($site->id, 'plugin:nexis/consent.privacy_url', ''),
            'gaId' => (string) $this->settings->get($site->id, 'plugin:nexis/consent.ga_measurement_id', ''),
            'gtmId' => (string) $this->settings->get($site->id, 'plugin:nexis/consent.gtm_id', ''),
            'adsId' => (string) $this->settings->get($site->id, 'plugin:nexis/consent.google_ads_id', ''),
            'localeFields' => $this->localeFieldsForSite($site),
            'saved' => RequestInput::query($request, 'saved') === '1',
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

        $gaId = $this->normalizeGoogleId(RequestInput::string($request, 'ga_measurement_id'), 'G-');
        $gtmId = $this->normalizeGoogleId(RequestInput::string($request, 'gtm_id'), 'GTM-');
        $adsId = $this->normalizeGoogleId(RequestInput::string($request, 'google_ads_id'), 'AW-');

        $rawGa = trim(RequestInput::string($request, 'ga_measurement_id'));
        $rawGtm = trim(RequestInput::string($request, 'gtm_id'));
        $rawAds = trim(RequestInput::string($request, 'google_ads_id'));
        if (
            ($rawGa !== '' && $gaId === '')
            || ($rawGtm !== '' && $gtmId === '')
            || ($rawAds !== '' && $adsId === '')
        ) {
            return $this->responses->redirect(
                $basePath . '/admin/consent?error=' . rawurlencode(
                    $this->ui->get($user, 'admin.error.consent_google_ids'),
                ),
            );
        }

        $this->settings->set($site->id, 'plugin:nexis/consent.enabled', RequestInput::string($request, 'enabled') === '1');
        $this->settings->set($site->id, 'plugin:nexis/consent.privacy_url', trim(RequestInput::string($request, 'privacy_url')));
        $this->settings->set($site->id, 'plugin:nexis/consent.ga_measurement_id', $gaId);
        $this->settings->set($site->id, 'plugin:nexis/consent.gtm_id', $gtmId);
        $this->settings->set($site->id, 'plugin:nexis/consent.google_ads_id', $adsId);

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        foreach (self::LOCALE_FIELDS as $field) {
            $values = is_array($body[$field] ?? null) ? $body[$field] : [];
            foreach ($site->enabledLocales() as $locale) {
                $code = $locale->locale;
                $this->settings->set(
                    $site->id,
                    'plugin:nexis/consent.' . $field . '.' . $code,
                    is_string($values[$code] ?? null) ? trim((string) $values[$code]) : '',
                );
            }
        }

        return $this->responses->redirect($basePath . '/admin/consent?saved=1');
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
        if (!$this->policy->can($user, $site, 'consent.manage')) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'consent.manage']), 403);
        }

        return [$user, $site, $basePath];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function localeFieldsForSite(Site $site): array
    {
        $defaults = [
            'text' => [
                'de' => 'Wir nutzen Google-Dienste für Statistik und/oder Marketing. Dafür benötigen wir Ihre Einwilligung (Opt-in). Technisch notwendige Cookies setzen wir ohne Einwilligung ein.',
                'en' => 'We use Google services for analytics and/or marketing. This requires your consent (opt-in). Strictly necessary cookies are set without consent.',
            ],
            'accept_label' => ['de' => 'Alle akzeptieren', 'en' => 'Accept all'],
            'reject_label' => ['de' => 'Nur notwendige', 'en' => 'Essential only'],
            'save_label' => ['de' => 'Auswahl speichern', 'en' => 'Save selection'],
            'privacy_label' => ['de' => 'Datenschutz', 'en' => 'Privacy'],
            'settings_label' => ['de' => 'Cookie-Einstellungen', 'en' => 'Cookie settings'],
            'analytics_label' => ['de' => 'Analyse (Google Analytics)', 'en' => 'Analytics (Google Analytics)'],
            'marketing_label' => ['de' => 'Marketing (Google Ads)', 'en' => 'Marketing (Google Ads)'],
        ];

        $out = [];
        foreach (self::LOCALE_FIELDS as $field) {
            $out[$field] = [];
            foreach ($site->enabledLocales() as $locale) {
                $code = $locale->locale;
                $fallback = $defaults[$field][$code]
                    ?? $defaults[$field]['de']
                    ?? '';
                $out[$field][$code] = (string) $this->settings->get(
                    $site->id,
                    'plugin:nexis/consent.' . $field . '.' . $code,
                    $fallback,
                );
            }
        }

        return $out;
    }

    private function normalizeGoogleId(string $raw, string $prefix): string
    {
        $raw = strtoupper(trim($raw));
        if ($raw === '') {
            return '';
        }
        if (!str_starts_with($raw, $prefix)) {
            return '';
        }
        if (preg_match('/^[A-Z0-9-]+$/', $raw) !== 1) {
            return '';
        }

        return $raw;
    }
}
