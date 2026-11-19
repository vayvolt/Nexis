<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Kernel\Nexis;

/**
 * Whether a plugin manifest/catalog compatibleCore matches the running CMS core.
 */
final class PluginCoreCompatibility
{
    public static function coreVersion(): string
    {
        return Nexis::VERSION;
    }

    public static function isCompatibleWithCore(string $compatibleCore, ?string $coreVersion = null): bool
    {
        $compatibleCore = trim($compatibleCore);
        if ($compatibleCore === '') {
            return false;
        }
        $coreVersion = trim($coreVersion ?? self::coreVersion());
        if ($coreVersion === '') {
            return false;
        }

        return SemVer::satisfies($coreVersion, $compatibleCore);
    }
}
