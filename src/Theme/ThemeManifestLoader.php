<?php

declare(strict_types=1);

namespace Nexis\Theme;

use InvalidArgumentException;

final class ThemeManifestLoader
{
    public function load(string $directory): ThemeManifest
    {
        $path = rtrim($directory, '\\/') . DIRECTORY_SEPARATOR . 'theme.json';
        if (!is_file($path)) {
            throw new InvalidArgumentException('theme.json fehlt: ' . $directory);
        }
        $json = file_get_contents($path);
        if ($json === false) {
            throw new InvalidArgumentException('theme.json unlesbar');
        }
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new InvalidArgumentException('theme.json ungültig');
        }
        foreach (['id', 'name', 'version', 'compatibleCore'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                throw new InvalidArgumentException('Theme-Feld fehlt: ' . $field);
            }
        }

        $templates = [];
        if (isset($data['templates']) && is_array($data['templates'])) {
            foreach ($data['templates'] as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $templates[$key] = $value;
                }
            }
        }

        $assets = [];
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (is_string($asset) && $asset !== '') {
                    $assets[] = $asset;
                }
            }
        }

        $slots = [];
        if (isset($data['slots']) && is_array($data['slots'])) {
            foreach ($data['slots'] as $slot) {
                if (is_string($slot)) {
                    $slots[] = $slot;
                }
            }
        }

        $extends = $data['extends'] ?? null;

        return new ThemeManifest(
            (string) $data['id'],
            (string) $data['name'],
            (string) $data['version'],
            (string) $data['compatibleCore'],
            is_string($extends) && $extends !== '' ? $extends : null,
            rtrim($directory, '\\/'),
            isset($data['tokens']) && is_string($data['tokens']) ? $data['tokens'] : 'tokens.json',
            $assets,
            $templates,
            $slots,
            $data,
        );
    }
}
