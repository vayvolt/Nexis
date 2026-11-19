<?php

declare(strict_types=1);

namespace Nexis\Plugin;

final class PluginManifest
{
    /**
     * @param array<string, mixed> $raw
     * @param list<string> $blocks
     * @param list<string> $permissions
     * @param list<string> $permissionRoles Role slugs that receive plugin permissions
     * @param list<string> $slots
     * @param list<string> $requiredPlugins Plugin ids that must be enabled before this one boots
     */
    public function __construct(
        public private(set) string $id,
        public private(set) string $name,
        public private(set) string $version,
        public private(set) string $compatibleCore,
        public private(set) string $php,
        public private(set) string $autoloadNamespace,
        public private(set) string $providerClass,
        public private(set) string $directory,
        public private(set) array $blocks,
        public private(set) array $permissions,
        public private(set) array $permissionRoles,
        public private(set) array $slots,
        public private(set) string $migrationsDir,
        public private(set) array $raw,
        public private(set) string $author = '',
        public private(set) ?string $uninstallClass = null,
        public private(set) array $requiredPlugins = [],
    ) {
    }
}
