<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;

/**
 * Typed accessor for one plugin's site_settings (keys under plugin:{id}.).
 */
final class PluginSettings
{
    public function __construct(
        private string $pluginId,
        private SettingsSchemaRegistry $schema,
        private PdoSiteSettingsRepository $store,
    ) {
    }

    public function pluginId(): string
    {
        return $this->pluginId;
    }

    public function get(SiteId $siteId, string $field, mixed $default = null): mixed
    {
        $meta = $this->schema->field($this->pluginId, $field);
        $fallback = $default;
        if ($fallback === null && $meta !== null) {
            $fallback = $meta->default;
        }
        $key = $this->schema->storageKey($this->pluginId, $field);

        return $this->store->get($siteId, $key, $fallback);
    }

    public function set(SiteId $siteId, string $field, mixed $value): void
    {
        $meta = $this->schema->field($this->pluginId, $field);
        $key = $this->schema->storageKey($this->pluginId, $field);
        $encrypt = $meta?->isSecret() ? true : null;
        $this->store->set($siteId, $key, $value, $encrypt);
    }

    /**
     * Registered fields only (missing keys use schema defaults).
     *
     * @return array<string, mixed>
     */
    public function all(SiteId $siteId): array
    {
        $out = [];
        foreach ($this->schema->fieldsFor($this->pluginId) as $name => $field) {
            $out[$name] = $this->get($siteId, $name, $field->default);
        }

        return $out;
    }

    public function delete(SiteId $siteId, string $field): void
    {
        $this->store->delete($siteId, $this->schema->storageKey($this->pluginId, $field));
    }
}
