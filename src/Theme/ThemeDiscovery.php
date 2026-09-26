<?php

declare(strict_types=1);

namespace Nexis\Theme;

final class ThemeDiscovery
{
    public function __construct(
        private string $themesRoot,
        private ThemeManifestLoader $loader,
    ) {
    }

    /**
     * @return list<ThemeManifest>
     */
    public function discover(): array
    {
        if (!is_dir($this->themesRoot)) {
            return [];
        }

        $manifests = [];
        $vendors = glob($this->themesRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        foreach ($vendors as $vendor) {
            $dirs = glob($vendor . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
            foreach ($dirs as $dir) {
                try {
                    $manifests[] = $this->loader->load($dir);
                } catch (\Throwable) {
                }
            }
        }
        usort($manifests, static fn (ThemeManifest $a, ThemeManifest $b): int => strcmp($a->id, $b->id));

        return $manifests;
    }

    public function find(string $themeKey): ?ThemeManifest
    {
        foreach ($this->discover() as $manifest) {
            if ($manifest->id === $themeKey) {
                return $manifest;
            }
        }

        return null;
    }
}
