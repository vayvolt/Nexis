<?php

declare(strict_types=1);

namespace Nexis\Plugins\Blog;

/**
 * URL prefix for blog posts and the public archive.
 */
final class BlogPaths
{
    public const BASE = 'blog';

    public static function archivePath(): string
    {
        return '/' . self::BASE;
    }

    public static function postPath(string $slugOrTitle): string
    {
        return \Nexis\Content\PagePath::under(self::BASE, $slugOrTitle);
    }
}
