<?php

declare(strict_types=1);

namespace Nexis\Plugin;

/**
 * Public URL prefixes for published plugin assets (assets/plugins/{id}/).
 */
final class PluginAssetRegistry
{
    /** @var array<string, string> */
    private array $prefixes = [];

    public function set(string $pluginId, string $urlPrefix): void
    {
        $prefix = rtrim(str_replace('\\', '/', $urlPrefix), '/');
        if ($prefix === '') {
            return;
        }
        $this->prefixes[$pluginId] = $prefix;
    }

    public function has(string $pluginId): bool
    {
        return isset($this->prefixes[$pluginId]);
    }

    /**
     * @return string Absolute-from-root path e.g. /assets/plugins/nexis/consent/consent.css
     */
    public function url(string $pluginId, string $relative = ''): string
    {
        $prefix = $this->prefixes[$pluginId]
            ?? '/assets/plugins/' . str_replace('\\', '/', $pluginId);
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '') {
            return $prefix;
        }

        return $prefix . '/' . $relative;
    }
}
