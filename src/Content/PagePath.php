<?php

declare(strict_types=1);

namespace Nexis\Content;

final class PagePath
{
    public static function fromSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $replaced = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = is_string($replaced) ? trim($replaced, '-') : '';
        if ($slug === '' || $slug === 'home') {
            return '/';
        }

        return '/' . $slug;
    }

    public static function slugFromPath(string $path): string
    {
        $trim = trim($path, '/');
        if ($trim === '') {
            return 'home';
        }

        $last = strrchr($trim, '/');

        return is_string($last) ? ltrim($last, '/') : $trim;
    }

    /**
     * Build a path under a fixed prefix, e.g. under("blog", "Hallo Welt") → /blog/hallo-welt.
     */
    public static function under(string $prefix, string $slug): string
    {
        $prefix = trim($prefix, '/');
        $leaf = self::fromSlug($slug);
        if ($prefix === '') {
            return $leaf;
        }
        if ($leaf === '/') {
            return '/' . $prefix;
        }

        return '/' . $prefix . $leaf;
    }

    /**
     * Replace the last path segment while keeping any parent prefix.
     */
    public static function replaceLeaf(string $path, string $slug): string
    {
        $trim = trim($path, '/');
        if ($trim === '' || !str_contains($trim, '/')) {
            return self::fromSlug($slug);
        }
        $parent = str_replace('\\', '/', dirname($trim));

        return self::under($parent, $slug);
    }
}
