<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;

/**
 * Site-level public account / SSO settings (stored in site_settings).
 */
final class AuthSettings
{
    public const REGISTRATION_ENABLED = 'auth.registration_enabled';
    public const PASSWORD_RESET_ENABLED = 'auth.password_reset_enabled';
    public const OIDC_ENABLED = 'auth.oidc.enabled';
    public const OIDC_ISSUER = 'auth.oidc.issuer';
    public const OIDC_CLIENT_ID = 'auth.oidc.client_id';
    public const OIDC_CLIENT_SECRET = 'auth.oidc.client_secret';
    public const OIDC_BUTTON_LABEL = 'auth.oidc.button_label';
    public const OIDC_AUTO_PROVISION = 'auth.oidc.auto_provision';
    public const OIDC_DEFAULT_ROLE = 'auth.oidc.default_role';

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

    public function oidcEnabled(SiteId $siteId): bool
    {
        return (bool) $this->settings->get($siteId, self::OIDC_ENABLED, false)
            && $this->oidcIssuer($siteId) !== ''
            && $this->oidcClientId($siteId) !== ''
            && $this->oidcClientSecret($siteId) !== '';
    }

    public function oidcConfigured(SiteId $siteId): bool
    {
        return (bool) $this->settings->get($siteId, self::OIDC_ENABLED, false);
    }

    public function oidcIssuer(SiteId $siteId): string
    {
        return trim((string) $this->settings->get($siteId, self::OIDC_ISSUER, ''));
    }

    public function oidcClientId(SiteId $siteId): string
    {
        return trim((string) $this->settings->get($siteId, self::OIDC_CLIENT_ID, ''));
    }

    public function oidcClientSecret(SiteId $siteId): string
    {
        return (string) $this->settings->get($siteId, self::OIDC_CLIENT_SECRET, '');
    }

    public function oidcButtonLabel(SiteId $siteId): string
    {
        return trim((string) $this->settings->get($siteId, self::OIDC_BUTTON_LABEL, ''));
    }

    public function oidcAutoProvision(SiteId $siteId): bool
    {
        return (bool) $this->settings->get($siteId, self::OIDC_AUTO_PROVISION, false);
    }

    public function oidcDefaultRoleSlug(SiteId $siteId): string
    {
        $slug = trim((string) $this->settings->get($siteId, self::OIDC_DEFAULT_ROLE, RoleSlug::MEMBER));
        if (!in_array($slug, RoleSlug::system(), true)) {
            return RoleSlug::MEMBER;
        }

        return $slug;
    }

    public function oidcProviderConfig(SiteId $siteId): OidcProviderConfig
    {
        return new OidcProviderConfig(
            $this->oidcIssuer($siteId),
            $this->oidcClientId($siteId),
            $this->oidcClientSecret($siteId),
        );
    }

    public function oidcLoginPolicy(SiteId $siteId): OidcLoginPolicy
    {
        return new OidcLoginPolicy(
            $this->oidcEnabled($siteId),
            $this->oidcAutoProvision($siteId),
            $this->oidcDefaultRoleSlug($siteId),
        );
    }

    public function setOidc(
        SiteId $siteId,
        bool $enabled,
        string $issuer,
        string $clientId,
        ?string $clientSecret,
        string $buttonLabel,
        bool $autoProvision,
        string $defaultRole,
    ): void {
        $issuer = rtrim(trim($issuer), '/');
        $clientId = trim($clientId);
        $buttonLabel = trim($buttonLabel);
        if (!in_array($defaultRole, RoleSlug::system(), true)) {
            $defaultRole = RoleSlug::MEMBER;
        }

        $this->settings->set($siteId, self::OIDC_ENABLED, $enabled);
        $this->settings->set($siteId, self::OIDC_ISSUER, $issuer);
        $this->settings->set($siteId, self::OIDC_CLIENT_ID, $clientId);
        if ($clientSecret !== null && trim($clientSecret) !== '') {
            $this->settings->set($siteId, self::OIDC_CLIENT_SECRET, trim($clientSecret));
        }
        $this->settings->set($siteId, self::OIDC_BUTTON_LABEL, $buttonLabel);
        $this->settings->set($siteId, self::OIDC_AUTO_PROVISION, $autoProvision);
        $this->settings->set($siteId, self::OIDC_DEFAULT_ROLE, $defaultRole);
    }
}
