<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Copies plugin resources/assets → assets/plugins/{vendor}/{name}/ for public serving.
 */
final class PluginAssetPublisher
{
    public function __construct(
        private string $projectRoot,
    ) {
    }

    /**
     * @return string Public URL prefix (e.g. /assets/plugins/nexis/consent) or empty if none
     */
    public function publish(PluginManifest $manifest): string
    {
        $src = $manifest->directory . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'assets';
        if (!is_dir($src)) {
            return '';
        }

        $relativeId = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $manifest->id);
        $dest = $this->projectRoot
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'plugins'
            . DIRECTORY_SEPARATOR . $relativeId;

        $this->syncDirectory($src, $dest);

        return '/assets/plugins/' . str_replace('\\', '/', $manifest->id);
    }

    /**
     * Removes previously published public assets for a plugin id (vendor/name).
     */
    public function unpublish(string $pluginId): void
    {
        $pluginId = trim(str_replace('\\', '/', $pluginId));
        if ($pluginId === '' || !str_contains($pluginId, '/') || str_contains($pluginId, '..')) {
            return;
        }
        $relativeId = str_replace('/', DIRECTORY_SEPARATOR, $pluginId);
        $dest = $this->projectRoot
            . DIRECTORY_SEPARATOR . 'assets'
            . DIRECTORY_SEPARATOR . 'plugins'
            . DIRECTORY_SEPARATOR . $relativeId;
        $this->deleteTree($dest);

        $vendorDir = dirname($dest);
        if (is_dir($vendorDir) && $this->isDirEmpty($vendorDir)) {
            @rmdir($vendorDir);
        }
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }

    private function isDirEmpty(string $path): bool
    {
        $items = scandir($path);

        return is_array($items) && count($items) <= 2;
    }

    private function syncDirectory(string $src, string $dest): void
    {
        if (!is_dir($dest) && !mkdir($dest, 0775, true) && !is_dir($dest)) {
            throw new RuntimeException('Plugin-Assets konnten nicht geschrieben werden: ' . $dest);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($src) + 1);
            $target = $dest . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
                    throw new RuntimeException('Plugin-Asset-Ordner fehlgeschlagen: ' . $target);
                }
                continue;
            }
            if (!$item->isFile()) {
                continue;
            }
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
                throw new RuntimeException('Plugin-Asset-Ordner fehlgeschlagen: ' . $parent);
            }
            $content = file_get_contents($item->getPathname());
            if (!is_string($content)) {
                continue;
            }
            $srcMtime = $item->getMTime();
            $srcSize = $item->getSize();
            if (
                is_file($target)
                && filesize($target) === $srcSize
                && filemtime($target) === $srcMtime
            ) {
                continue;
            }
            if (file_put_contents($target, $content) !== false) {
                @touch($target, $srcMtime);
            }
        }
    }
}
