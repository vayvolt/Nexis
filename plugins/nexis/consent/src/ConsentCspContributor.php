<?php

declare(strict_types=1);

namespace Nexis\Plugins\Consent;

use Nexis\Http\ContentSecurityPolicy;
use Nexis\Http\CspContributor;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;

/**
 * Widens CSP only when Consent is enabled and Google tags are configured.
 */
final class ConsentCspContributor implements CspContributor
{
    public function __construct(
        private SiteRepository $sites,
        private PdoSiteSettingsRepository $settings,
    ) {
    }

    public function contribute(ContentSecurityPolicy $csp): void
    {
        $site = $this->sites->installed();
        if (!$site instanceof Site) {
            return;
        }
        if (!$this->truthy($this->settings->get($site->id, 'plugin:nexis/consent.enabled', false))) {
            return;
        }

        $ga = trim((string) $this->settings->get($site->id, 'plugin:nexis/consent.ga_measurement_id', ''));
        $gtm = trim((string) $this->settings->get($site->id, 'plugin:nexis/consent.gtm_id', ''));
        $ads = trim((string) $this->settings->get($site->id, 'plugin:nexis/consent.google_ads_id', ''));
        if ($ga === '' && $gtm === '' && $ads === '') {
            return;
        }

        $csp->allowScript(
            "'unsafe-inline'",
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
        );
        $csp->allowConnect(
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
            'https://analytics.google.com',
            'https://region1.google-analytics.com',
        );
        $csp->allowFrame('https://www.googletagmanager.com');
        $csp->allowImg(
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
        );
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value !== 0.0;
        }
        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
