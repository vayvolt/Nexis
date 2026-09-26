<?php

declare(strict_types=1);

namespace Nexis\Kernel;

/**
 * Product identity and core SemVer. Keep in sync with plugin compatibleCore (^0.3).
 */
final class Nexis
{
    public const NAME = 'Nexis';

    public const VERSION = '0.3.0';

    public const TAGLINE = 'Individualisierbarer Homepage-Builder';

    public const VENDOR = 'Vayvolt';

    public const VENDOR_URL = 'https://www.vayvolt.de';

    /** Public portal (downloads, plugin directory, wiki). */
    public const PORTAL_URL = 'https://nexis.vayvolt.de';

    /** Canonical public source repository. */
    public const SOURCE_URL = 'https://github.com/vayvolt/Nexis';

    /** German product attribution line for UI footers. */
    public const ATTRIBUTION = 'Ein Produkt von Vayvolt';

    /** SPDX identifier. Full text: /LICENSE (GNU GPL v2). */
    public const LICENSE = 'GPL-2.0-or-later';

    public const LICENSE_NAME = 'GNU General Public License v2.0 or later';

    public const LICENSE_URI = 'https://www.gnu.org/licenses/gpl-2.0.html';

    public const COPYRIGHT_YEAR = '2026';

    /** Public path (relative to site root) for favicon / compact mark. */
    public const BRAND_ICON = 'assets/brand/nexis-icon.svg';

    /** Public path (relative to site root) for horizontal wordmark. */
    public const BRAND_LOGO = 'assets/brand/nexis-logo.svg';

    public static function brandUrl(string $basePath, string $asset = self::BRAND_LOGO): string
    {
        $root = rtrim($basePath, '/');

        return ($root !== '' ? $root . '/' : '/') . ltrim($asset, '/');
    }

    public static function copyrightNotice(): string
    {
        return 'Copyright (C) ' . self::COPYRIGHT_YEAR . ' ' . self::VENDOR;
    }
}
