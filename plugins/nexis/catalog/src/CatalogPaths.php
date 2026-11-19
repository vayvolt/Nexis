<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

/**
 * URL prefix for products and the public catalog archive.
 */
final class CatalogPaths
{
    public const BASE = 'catalog';

    public static function archivePath(): string
    {
        return '/' . self::BASE;
    }

    public static function productPath(string $slugOrTitle): string
    {
        return \Nexis\Content\PagePath::under(self::BASE, $slugOrTitle);
    }
}
