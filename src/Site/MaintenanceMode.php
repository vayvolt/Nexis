<?php

declare(strict_types=1);

namespace Nexis\Site;

/**
 * Site maintenance flag stored in site_settings.
 */
final class MaintenanceMode
{
    public const KEY_ENABLED = 'site.maintenance.enabled';
    public const KEY_MESSAGE = 'site.maintenance.message';

    public function __construct(
        private PdoSiteSettingsRepository $settings,
    ) {
    }

    public function isEnabled(SiteId $siteId): bool
    {
        return (bool) $this->settings->get($siteId, self::KEY_ENABLED, false);
    }

    public function message(SiteId $siteId): string
    {
        $raw = $this->settings->get($siteId, self::KEY_MESSAGE, '');

        return is_string($raw) ? trim($raw) : '';
    }

    public function setEnabled(SiteId $siteId, bool $enabled): void
    {
        $this->settings->set($siteId, self::KEY_ENABLED, $enabled);
    }

    public function setMessage(SiteId $siteId, string $message): void
    {
        $this->settings->set($siteId, self::KEY_MESSAGE, mb_substr(trim($message), 0, 2000));
    }
}
