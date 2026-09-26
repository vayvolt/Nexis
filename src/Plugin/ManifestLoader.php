<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use InvalidArgumentException;

final class ManifestLoader
{
    public function load(string $directory): PluginManifest
    {
        $path = rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . 'plugin.json';
        if (!is_file($path)) {
            throw new InvalidArgumentException('plugin.json fehlt: ' . $directory);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new InvalidArgumentException('plugin.json unlesbar: ' . $path);
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('plugin.json ungültig: ' . $path);
        }

        foreach (['id', 'name', 'version', 'compatibleCore', 'autoload', 'provider'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException('Manifest-Feld fehlt: ' . $field);
            }
        }

        $provides = $data['provides'] ?? [];
        if (!is_array($provides)) {
            $provides = [];
        }

        $author = '';
        if (isset($data['author']) && is_string($data['author'])) {
            $author = trim($data['author']);
        } elseif (isset($data['author']) && is_array($data['author'])) {
            $name = $data['author']['name'] ?? null;
            if (is_string($name)) {
                $author = trim($name);
            }
        }

        $requires = $data['requires'] ?? [];
        if (!is_array($requires)) {
            $requires = [];
        }

        return new PluginManifest(
            (string) $data['id'],
            (string) $data['name'],
            (string) $data['version'],
            (string) $data['compatibleCore'],
            isset($data['php']) && is_string($data['php']) ? $data['php'] : '>=8.4',
            rtrim((string) $data['autoload'], '\\') . '\\',
            (string) $data['provider'],
            rtrim($directory, '\\/'),
            $this->stringList($provides['blocks'] ?? []),
            $this->stringList($provides['permissions'] ?? []),
            $this->permissionRoles($provides),
            $this->stringList($provides['slots'] ?? []),
            isset($data['migrations']) && is_string($data['migrations']) ? $data['migrations'] : 'migrations',
            $data,
            $author,
            isset($data['uninstall']) && is_string($data['uninstall']) && $data['uninstall'] !== ''
                ? $data['uninstall']
                : null,
            $this->stringList($requires['plugins'] ?? []),
        );
    }

    /**
     * @param array<mixed> $provides
     * @return list<string>
     */
    private function permissionRoles(array $provides): array
    {
        $roles = $this->stringList($provides['permissionRoles'] ?? []);
        if ($roles !== []) {
            return $roles;
        }

        return ['admin', 'editor'];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }
}
