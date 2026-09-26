<?php

declare(strict_types=1);

namespace Nexis\Plugins\Consent;

use Nexis\I18n\PublicUi;
use Nexis\Plugin\PluginAssetRegistry;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;

final class ConsentBanner
{
    public const COOKIE = 'nexis_consent';

    public function __construct(
        private SiteRepository $sites,
        private PdoSiteSettingsRepository $settings,
        private PublicUi $ui,
        private PluginAssetRegistry $assets,
    ) {
    }

    /**
     * True when GA4, GTM or Google Ads is configured (requires opt-in consent under TTDSG/DSGVO).
     */
    public function trackingConfigured(Site $site): bool
    {
        $config = $this->googleConfig($site);

        return $config['gaId'] !== '' || $config['gtmId'] !== '' || $config['adsId'] !== '';
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function headHtml(array $ctx): string
    {
        if (!$this->shouldRenderConsentUi()) {
            return '';
        }
        $site = $this->sites->installed();
        if (!$site instanceof Site) {
            return '';
        }

        $basePath = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $config = $this->googleConfig($site);
        $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

        $html = '<!-- nexis/consent: Google Consent Mode v2 defaults -->' . "\n";
        $html .= '<script>'
            . 'window.dataLayer=window.dataLayer||[];'
            . 'function gtag(){dataLayer.push(arguments);}'
            . 'gtag("consent","default",{'
            . 'ad_storage:"denied",'
            . 'ad_user_data:"denied",'
            . 'ad_personalization:"denied",'
            . 'analytics_storage:"denied",'
            . 'functionality_storage:"granted",'
            . 'security_storage:"granted",'
            . 'wait_for_update:500'
            . '});'
            . 'window.__nxConsentConfig=' . json_encode($config, $jsonFlags) . ';'
            . '</script>' . "\n";

        if ($config['gtmId'] !== '') {
            $gtm = htmlspecialchars($config['gtmId'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});'
                . 'var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';'
                . 'j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;'
                . 'f.parentNode.insertBefore(j,f);})(window,document,\'script\',\'dataLayer\',\'' . $gtm . '\');</script>' . "\n";
        } elseif ($config['gaId'] !== '') {
            $ga = htmlspecialchars($config['gaId'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga . '"></script>' . "\n";
            $html .= '<script>gtag("js",new Date());gtag("config","' . $ga . '");';
            if ($config['adsId'] !== '') {
                $ads = htmlspecialchars($config['adsId'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html .= 'gtag("config","' . $ads . '");';
            }
            $html .= '</script>' . "\n";
        } elseif ($config['adsId'] !== '') {
            $ads = htmlspecialchars($config['adsId'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ads . '"></script>' . "\n";
            $html .= '<script>gtag("js",new Date());gtag("config","' . $ads . '");</script>' . "\n";
        }

        $html .= '<link rel="stylesheet" href="' . $basePath . $this->assets->url('nexis/consent', 'consent.css') . '">' . "\n"
            . '<script src="' . $basePath . $this->assets->url('nexis/consent', 'consent.js') . '" defer></script>';

        return $html;
    }

    /**
     * @param array<string, mixed> $ctx
     */
    public function bannerHtml(array $ctx): string
    {
        if (!$this->shouldRenderConsentUi()) {
            return '';
        }
        $site = $this->sites->installed();
        if (!$site instanceof Site) {
            return '';
        }
        $locale = is_string($ctx['locale'] ?? null) ? (string) $ctx['locale'] : $site->defaultLocale;
        $text = $this->localized($site, 'text', $locale, $this->ui->get($locale, 'public.consent.text'));
        $accept = $this->localized($site, 'accept_label', $locale, $this->ui->get($locale, 'public.consent.accept'));
        $reject = $this->localized($site, 'reject_label', $locale, $this->ui->get($locale, 'public.consent.reject'));
        $save = $this->localized($site, 'save_label', $locale, $this->ui->get($locale, 'public.consent.save'));
        $analyticsLabel = $this->localized($site, 'analytics_label', $locale, $this->ui->get($locale, 'public.consent.analytics'));
        $marketingLabel = $this->localized($site, 'marketing_label', $locale, $this->ui->get($locale, 'public.consent.marketing'));
        $privacyUrl = trim((string) $this->settings->get($site->id, 'plugin:nexis/consent.privacy_url', ''));
        $privacyLabel = $this->localized($site, 'privacy_label', $locale, $this->ui->get($locale, 'public.consent.privacy'));
        $config = $this->googleConfig($site);
        $showAnalytics = $config['gaId'] !== '' || $config['gtmId'] !== '';
        $showMarketing = $config['adsId'] !== '' || $config['gtmId'] !== '';

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<div class="nx-consent" id="nx-consent" hidden'
            . ' data-cookie="' . $e(self::COOKIE) . '"'
            . ' data-has-analytics="' . ($showAnalytics ? '1' : '0') . '"'
            . ' data-has-marketing="' . ($showMarketing ? '1' : '0') . '"'
            . ' role="dialog" aria-live="polite" aria-label="' . $e($this->ui->get($locale, 'public.consent.aria')) . '">';
        $html .= '<div class="nx-consent__inner">';
        $html .= '<div class="nx-consent__copy">';
        $html .= '<p class="nx-consent__text">' . $e($text) . '</p>';
        if ($showAnalytics || $showMarketing) {
            $html .= '<div class="nx-consent__cats" data-nx-consent-cats>';
            if ($showAnalytics) {
                $html .= '<label class="nx-consent__cat"><input type="checkbox" data-nx-cat="analytics" checked> '
                    . $e($analyticsLabel) . '</label>';
            }
            if ($showMarketing) {
                $html .= '<label class="nx-consent__cat"><input type="checkbox" data-nx-cat="marketing"> '
                    . $e($marketingLabel) . '</label>';
            }
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '<div class="nx-consent__actions">';
        if ($privacyUrl !== '') {
            $html .= '<a class="nx-consent__link" href="' . $e($privacyUrl) . '">' . $e($privacyLabel) . '</a>';
        }
        $html .= '<button type="button" class="nx-consent__btn nx-consent__btn--ghost" data-nx-consent-reject>'
            . $e($reject) . '</button>';
        if ($showAnalytics || $showMarketing) {
            $html .= '<button type="button" class="nx-consent__btn nx-consent__btn--secondary" data-nx-consent-save>'
                . $e($save) . '</button>';
        }
        $html .= '<button type="button" class="nx-consent__btn nx-consent__btn--primary" data-nx-consent-accept>'
            . $e($accept) . '</button>';
        $html .= '</div></div></div>';

        if ($config['gtmId'] !== '') {
            $gtm = $e($config['gtmId']);
            $html .= '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $gtm
                . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
        }

        return $html;
    }

    /**
     * Persistent footer control to reopen cookie preferences.
     *
     * @param array<string, mixed> $ctx
     */
    public function settingsLinkHtml(array $ctx): string
    {
        if (!$this->shouldRenderConsentUi()) {
            return '';
        }
        $site = $this->sites->installed();
        if (!$site instanceof Site) {
            return '';
        }
        $locale = is_string($ctx['locale'] ?? null) ? (string) $ctx['locale'] : $site->defaultLocale;
        $label = $this->localized($site, 'settings_label', $locale, $this->ui->get($locale, 'public.consent.settings'));
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<a href="#cookies" class="nx-consent-settings" data-nx-consent-open>'
            . $e($label) . '</a>';
    }

    /**
     * @return array{gaId: string, gtmId: string, adsId: string}
     */
    private function googleConfig(Site $site): array
    {
        return [
            'gaId' => $this->normalizeId(
                (string) $this->settings->get($site->id, 'plugin:nexis/consent.ga_measurement_id', ''),
                'G-',
            ),
            'gtmId' => $this->normalizeId(
                (string) $this->settings->get($site->id, 'plugin:nexis/consent.gtm_id', ''),
                'GTM-',
            ),
            'adsId' => $this->normalizeId(
                (string) $this->settings->get($site->id, 'plugin:nexis/consent.google_ads_id', ''),
                'AW-',
            ),
        ];
    }

    private function normalizeId(string $raw, string $prefix): string
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

    private function shouldRenderConsentUi(): bool
    {
        $site = $this->sites->installed();
        if (!$site instanceof Site) {
            return false;
        }
        if (!$this->enabled($site)) {
            return false;
        }

        return $this->trackingConfigured($site);
    }

    private function enabled(Site $site): bool
    {
        return (bool) $this->settings->get($site->id, 'plugin:nexis/consent.enabled', true);
    }

    private function localized(Site $site, string $field, string $locale, string $default): string
    {
        $key = 'plugin:nexis/consent.' . $field . '.' . $locale;
        $value = $this->settings->get($site->id, $key, null);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        $fallback = $this->settings->get($site->id, 'plugin:nexis/consent.' . $field . '.' . $site->defaultLocale, null);
        if (is_string($fallback) && trim($fallback) !== '') {
            return trim($fallback);
        }

        return $default;
    }
}
