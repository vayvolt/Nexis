<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Auth\PdoPermissionLookup;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PluginToggled;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class PluginUninstaller
{
    public function __construct(
        private PluginDiscovery $discovery,
        private PluginCatalog $catalog,
        private EventDispatcher $events,
        private ContainerInterface $container,
        private LoggerInterface $logger,
        private string $rootPath,
        private PdoPermissionLookup $permissions,
        private PdoSiteSettingsRepository $settings,
        private SettingsSchemaRegistry $settingsSchema,
        private PluginAssetPublisher $assets,
    ) {
    }

    /**
     * Removes site data, catalog entry, published assets, and the plugin package on disk.
     */
    public function uninstall(SiteId $siteId, string $pluginKey, ?\Nexis\Auth\UserId $actorId = null): void
    {
        $pluginKey = trim($pluginKey);
        if ($pluginKey === '' || !str_contains($pluginKey, '/') || str_contains($pluginKey, '..')) {
            throw new RuntimeException('Ungültige Plugin-ID.');
        }

        $manifest = null;
        foreach ($this->discovery->discover() as $item) {
            if ($item->id === $pluginKey) {
                $manifest = $item;
                break;
            }
        }

        $wasEnabled = in_array($pluginKey, $this->catalog->enabledKeys($siteId), true);
        if ($wasEnabled) {
            $this->catalog->setStatus($siteId, $pluginKey, PluginInstallStatus::Disabled);
            if ($actorId !== null) {
                $this->events->dispatch(new PluginToggled($siteId, $pluginKey, false, $actorId));
            }
        }

        $pluginDir = $manifest instanceof PluginManifest ? $manifest->directory : null;

        if ($manifest instanceof PluginManifest) {
            $this->runHandler($manifest, $siteId);
            if ($manifest->permissions !== []) {
                $this->permissions->revokeForSite($siteId, $manifest->permissions);
            }
        }

        $this->settings->deleteByKeyPrefix($siteId, $this->settingsSchema->storagePrefix($pluginKey));
        $this->clearPluginStorage($pluginKey);
        $this->catalog->removePlugin($pluginKey);
        $this->assets->unpublish($pluginKey);

        if (is_string($pluginDir) && $pluginDir !== '') {
            $this->deletePluginPackage($pluginKey, $pluginDir);
        } else {
            $this->deletePluginPackage($pluginKey, $this->expectedPluginDir($pluginKey));
        }
    }

    private function runHandler(PluginManifest $manifest, SiteId $siteId): void
    {
        $class = $manifest->uninstallClass;
        if ($class === null || $class === '') {
            return;
        }

        $this->registerAutoload($manifest);
        $this->requirePluginClassFile($manifest, $class);
        if (!class_exists($class, false)) {
            $this->logger->warning('Plugin uninstall handler missing', [
                'plugin' => $manifest->id,
                'class' => $class,
            ]);

            return;
        }

        try {
            $handler = new $class();
            if (!$handler instanceof UninstallHandler) {
                throw new RuntimeException('UninstallHandler-Interface fehlt: ' . $class);
            }
            $handler->uninstall($siteId, $this->container);
        } catch (Throwable $e) {
            $this->logger->error('Plugin uninstall handler failed', [
                'plugin' => $manifest->id,
                'exception' => $e->getMessage(),
            ]);
            throw new RuntimeException('Deinstallation fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }
    }

    private function clearPluginStorage(string $pluginKey): void
    {
        [$vendor, $name] = array_pad(explode('/', $pluginKey, 2), 2, '');
        if ($vendor === '' || $name === '') {
            return;
        }
        $path = $this->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'plugin'
            . DIRECTORY_SEPARATOR . $vendor . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) {
            return;
        }
        $this->deleteTree($path);
        $vendorDir = dirname($path);
        if (is_dir($vendorDir) && $this->isDirEmpty($vendorDir)) {
            @rmdir($vendorDir);
        }
    }

    private function expectedPluginDir(string $pluginKey): string
    {
        [$vendor, $name] = explode('/', $pluginKey, 2);

        return $this->rootPath . DIRECTORY_SEPARATOR . 'plugins'
            . DIRECTORY_SEPARATOR . $vendor
            . DIRECTORY_SEPARATOR . $name;
    }

    private function deletePluginPackage(string $pluginKey, string $pluginDir): void
    {
        $pluginsRoot = $this->normalizePath(
            $this->rootPath . DIRECTORY_SEPARATOR . 'plugins',
        );
        $target = $this->normalizePath($pluginDir);
        if ($target === '' || $pluginsRoot === '') {
            return;
        }
        $prefix = $pluginsRoot . DIRECTORY_SEPARATOR;
        if ($target === $pluginsRoot || !str_starts_with($target, $prefix)) {
            $this->logger->warning('Plugin package path rejected on uninstall', [
                'plugin' => $pluginKey,
                'path' => $pluginDir,
            ]);

            return;
        }

        // Must be exactly plugins/{vendor}/{name} — never delete a parent.
        $relative = substr($target, strlen($prefix));
        if ($relative === '' || substr_count($relative, DIRECTORY_SEPARATOR) !== 1) {
            $this->logger->warning('Plugin package path depth rejected on uninstall', [
                'plugin' => $pluginKey,
                'path' => $pluginDir,
            ]);

            return;
        }

        $this->deleteTree($target);
        $vendorDir = dirname($target);
        if (is_dir($vendorDir) && $this->isDirEmpty($vendorDir)) {
            @rmdir($vendorDir);
        }
    }

    private function registerAutoload(PluginManifest $manifest): void
    {
        $prefix = $manifest->autoloadNamespace;
        $baseDir = $manifest->directory . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }, true, true);
    }

    private function requirePluginClassFile(PluginManifest $manifest, string $class): void
    {
        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            return;
        }

        $prefix = $manifest->autoloadNamespace;
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $manifest->directory . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

    private function normalizePath(string $path): string
    {
        $real = realpath($path);
        if ($real !== false) {
            return $real;
        }

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private function isDirEmpty(string $path): bool
    {
        $items = scandir($path);

        return is_array($items) && count($items) <= 2;
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
}
