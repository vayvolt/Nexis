<?php

declare(strict_types=1);

namespace Nexis\Auth;

/**
 * Core CMS permission keys only. Plugin permissions are registered via
 * PluginKernel::registerPermissions() / plugin.json provides.permissions.
 */
final class Permission
{
    public const CONTENT_PAGE_EDIT = 'content.page.edit';
    public const CONTENT_PAGE_SEO = 'content.page.seo';
    public const CONTENT_PAGE_SUBMIT_REVIEW = 'content.page.submit_review';
    public const CONTENT_PAGE_PUBLISH = 'content.page.publish';
    public const CONTENT_MEDIA_MANAGE = 'content.media.manage';
    public const CONTENT_MENU_MANAGE = 'content.menu.manage';
    public const THEME_MANAGE = 'theme.manage';
    public const THEME_CUSTOM_CSS = 'theme.custom_css';
    public const PLUGIN_MANAGE = 'plugin.manage';
    public const PLUGIN_INSTALL = 'plugin.install';
    public const SETTINGS_MANAGE = 'settings.manage';
    public const USERS_MANAGE = 'users.manage';
    public const AUDIT_VIEW = 'audit.view';
    public const WEBHOOKS_MANAGE = 'webhooks.manage';
    public const EXPORT_MANAGE = 'export.manage';

    /**
     * @return list<string>
     */
    public static function core(): array
    {
        return [
            self::CONTENT_PAGE_EDIT,
            self::CONTENT_PAGE_SEO,
            self::CONTENT_PAGE_SUBMIT_REVIEW,
            self::CONTENT_PAGE_PUBLISH,
            self::CONTENT_MEDIA_MANAGE,
            self::CONTENT_MENU_MANAGE,
            self::THEME_MANAGE,
            self::THEME_CUSTOM_CSS,
            self::PLUGIN_MANAGE,
            self::PLUGIN_INSTALL,
            self::SETTINGS_MANAGE,
            self::USERS_MANAGE,
            self::AUDIT_VIEW,
            self::WEBHOOKS_MANAGE,
            self::EXPORT_MANAGE,
        ];
    }

    /**
     * Default grants for the site "editor" / Redakteur role (core only).
     * May edit content and submit for review; publishing stays with admins.
     *
     * @return list<string>
     */
    public static function editorDefaults(): array
    {
        return [
            self::CONTENT_PAGE_EDIT,
            self::CONTENT_PAGE_SEO,
            self::CONTENT_PAGE_SUBMIT_REVIEW,
            self::CONTENT_MEDIA_MANAGE,
            self::CONTENT_MENU_MANAGE,
        ];
    }

    /**
     * Default grants for the site "seo" role (core only).
     * SEO meta and media (alt text); no block editing, menus, or publish.
     *
     * @return list<string>
     */
    public static function seoDefaults(): array
    {
        return [
            self::CONTENT_PAGE_SEO,
            self::CONTENT_MEDIA_MANAGE,
        ];
    }
}
