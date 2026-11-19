<?php

declare(strict_types=1);

namespace Nexis\Auth;

/**
 * Out-of-the-box system role templates for a site (admin, Redakteur, SEO, Mitglied).
 *
 * @phpstan-type RoleTemplate array{slug: string, name: string, permissions: list<string>}
 */
final class SystemRoleTemplates
{
    /**
     * @return list<RoleTemplate>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => RoleSlug::ADMIN,
                'name' => 'Website-Admin',
                'permissions' => Permission::core(),
            ],
            [
                'slug' => RoleSlug::EDITOR,
                'name' => 'Redakteur',
                'permissions' => Permission::editorDefaults(),
            ],
            [
                'slug' => RoleSlug::SEO,
                'name' => 'SEO',
                'permissions' => Permission::seoDefaults(),
            ],
            [
                'slug' => RoleSlug::MEMBER,
                'name' => 'Mitglied',
                'permissions' => [],
            ],
        ];
    }

    /**
     * Plugin permission keys that the SEO template should receive when present.
     *
     * @return list<string>
     */
    public static function seoPluginExtras(): array
    {
        return [
            'redirects.manage',
        ];
    }
}
