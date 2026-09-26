<?php

declare(strict_types=1);

namespace Nexis\Plugin;

final class PluginDiscovery
{
    public function __construct(
        private string $pluginsRoot,
        private ManifestLoader $loader,
    ) {
    }

    /**
     * @return list<PluginManifest>
     */
    public function discover(): array
    {
        if (!is_dir($this->pluginsRoot)) {
            return [];
        }

        $manifests = [];
        $vendorDirs = glob($this->pluginsRoot . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        foreach ($vendorDirs as $vendorDir) {
            $pluginDirs = glob($vendorDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
            foreach ($pluginDirs as $pluginDir) {
                try {
                    $manifests[] = $this->loader->load($pluginDir);
                } catch (\Throwable) {
                    // fail-soft: ungültige Manifeste werden übersprungen
                }
            }
        }

        usort(
            $manifests,
            static fn (PluginManifest $a, PluginManifest $b): int => strcmp($a->id, $b->id),
        );

        return $manifests;
    }
}
