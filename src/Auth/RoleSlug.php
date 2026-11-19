<?php

declare(strict_types=1);

namespace Nexis\Auth;

/**
 * Built-in role slugs. Member has site membership but no CMS permissions.
 */
final class RoleSlug
{
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const SEO = 'seo';
    public const MEMBER = 'member';

    /**
     * @return list<string>
     */
    public static function system(): array
    {
        return [
            self::ADMIN,
            self::EDITOR,
            self::SEO,
            self::MEMBER,
        ];
    }
}
