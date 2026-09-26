<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;

/**
 * Site-level public account settings (stored in site_settings).
 */
final class AuthSettings
{
    public const REGISTRATION_ENABLED = 'auth.registration_enabled';
    public const PASSWORD_RESET_ENABLED = 'auth.password_reset_enabled';

    public function __construct(
        private PdoSiteSettingsRepository $settings,
    ) {
    }

    public function registrationEnabled(SiteId $siteId): bool
    {
        return (bool) $this->settings->get($siteId, self::REGISTRATION_ENABLED, false);
    }

    public function passwordResetEnabled(SiteId $siteId): bool
    {
        return (bool) $this->settings->get($siteId, self::PASSWORD_RESET_ENABLED, true);
    }

    public function setRegistrationEnabled(SiteId $siteId, bool $enabled): void
    {
        $this->settings->set($siteId, self::REGISTRATION_ENABLED, $enabled);
    }

    public function setPasswordResetEnabled(SiteId $siteId, bool $enabled): void
    {
        $this->settings->set($siteId, self::PASSWORD_RESET_ENABLED, $enabled);
    }
}
