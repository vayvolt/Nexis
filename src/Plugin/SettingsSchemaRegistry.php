<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use InvalidArgumentException;

/**
 * Registry of plugin setting schemas registered during boot.
 */
final class SettingsSchemaRegistry
{
    /**
     * @var array<string, array<string, SettingsField>> pluginId => fieldName => field
     */
    private array $schemas = [];

    /**
     * @param array<string, array{type?: string, default?: mixed, label?: string}|SettingsField> $fields
     */
    public function register(string $pluginId, array $fields): void
    {
        $pluginId = trim($pluginId);
        if ($pluginId === '' || !str_contains($pluginId, '/')) {
            throw new InvalidArgumentException('Invalid plugin id for settings schema.');
        }
        foreach ($fields as $name => $spec) {
            if ($spec instanceof SettingsField) {
                $field = $spec;
            } else {
                if (!is_string($name) || trim($name) === '') {
                    continue;
                }
                $name = trim($name);
                if (!is_array($spec)) {
                    $spec = [];
                }
                $type = isset($spec['type']) && is_string($spec['type']) ? trim($spec['type']) : 'string';
                if ($type === '') {
                    $type = 'string';
                }
                $field = new SettingsField(
                    $name,
                    $type,
                    $spec['default'] ?? null,
                    isset($spec['label']) && is_string($spec['label']) ? $spec['label'] : '',
                );
            }
            $this->schemas[$pluginId][$field->name] = $field;
        }
    }

    public function has(string $pluginId, string $field): bool
    {
        return isset($this->schemas[$pluginId][$field]);
    }

    public function field(string $pluginId, string $field): ?SettingsField
    {
        return $this->schemas[$pluginId][$field] ?? null;
    }

    /**
     * @return array<string, SettingsField>
     */
    public function fieldsFor(string $pluginId): array
    {
        return $this->schemas[$pluginId] ?? [];
    }

    public function storageKey(string $pluginId, string $field): string
    {
        return 'plugin:' . $pluginId . '.' . $field;
    }

    public function storagePrefix(string $pluginId): string
    {
        return 'plugin:' . $pluginId . '.';
    }

    /**
     * @return list<string>
     */
    public function pluginIds(): array
    {
        return array_keys($this->schemas);
    }

    public function hasSchema(string $pluginId): bool
    {
        return ($this->schemas[$pluginId] ?? []) !== [];
    }
}
