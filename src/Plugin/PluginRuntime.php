<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\UserRepository;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\Translator;
use Nexis\Kernel\Nexis;
use Nexis\Site\SiteRepository;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class PluginRuntime
{
    private bool $booted = false;

    public function __construct(
        private PluginDiscovery $discovery,
        private PluginCatalog $catalog,
        private PluginMigrator $migrator,
        private PluginKernel $kernel,
        private SiteRepository $sites,
        private LoggerInterface $logger,
        private ContainerInterface $container,
        private PluginSignatureVerifier $signatures,
        private Translator $translator,
        private PluginAssetPublisher $assets,
        private PluginAssetRegistry $assetRegistry,
        private string $coreVersion = Nexis::VERSION,
    ) {
    }

    public function bootOnce(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        $this->boot();
    }

    public function boot(): void
    {
        $manifests = $this->discovery->discover();
        try {
            $this->catalog->sync($manifests);
        } catch (Throwable $e) {
            $this->logger->warning('Plugin catalog sync failed', ['exception' => $e->getMessage()]);

            return;
        }

        $site = $this->sites->installed();
        if ($site === null) {
            return;
        }

        foreach ($manifests as $manifest) {
            $this->translator->addPath(
                $manifest->directory . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang',
            );
        }

        $enabled = array_fill_keys($this->catalog->enabledKeys($site->id), true);
        $byId = [];
        foreach ($manifests as $manifest) {
            $byId[$manifest->id] = $manifest;
        }

        /** @var array<string, PluginManifest> $pending */
        $pending = [];
        foreach ($enabled as $pluginKey => $_) {
            $manifest = $byId[$pluginKey] ?? null;
            if ($manifest instanceof PluginManifest) {
                $pending[$pluginKey] = $manifest;
            } else {
                $this->logger->warning('Enabled plugin not found on disk; skipped', [
                    'plugin' => $pluginKey,
                ]);
            }
        }

        /** @var array<string, true> $booted */
        $booted = [];
        $maxPasses = count($pending) + 1;
        for ($pass = 0; $pass < $maxPasses && $pending !== []; $pass++) {
            $progress = false;
            foreach ($pending as $pluginKey => $manifest) {
                $hardMissing = [];
                foreach ($manifest->requiredPlugins as $req) {
                    if (!isset($enabled[$req])) {
                        $hardMissing[] = $req;
                    }
                }
                if ($hardMissing !== []) {
                    $this->logger->error('Plugin skipped: required plugins not enabled', [
                        'plugin' => $manifest->id,
                        'missing' => $hardMissing,
                    ]);
                    unset($pending[$pluginKey]);
                    $progress = true;
                    continue;
                }

                $waiting = [];
                foreach ($manifest->requiredPlugins as $req) {
                    if (!isset($booted[$req])) {
                        $waiting[] = $req;
                    }
                }
                if ($waiting !== []) {
                    continue;
                }

                try {
                    $this->bootPlugin($manifest);
                    $booted[$pluginKey] = true;
                } catch (Throwable $e) {
                    $this->logger->error('Plugin boot failed', [
                        'plugin' => $manifest->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
                unset($pending[$pluginKey]);
                $progress = true;
            }
            if (!$progress) {
                foreach ($pending as $manifest) {
                    $this->logger->error('Plugin skipped: circular or unresolved requires.plugins', [
                        'plugin' => $manifest->id,
                        'requires' => $manifest->requiredPlugins,
                    ]);
                }
                break;
            }
        }

        try {
            $this->kernel->syncPermissions(
                $this->container->get(PdoPermissionLookup::class),
                $this->container->get(UserRepository::class),
                $site->id,
            );
        } catch (Throwable $e) {
            $this->logger->warning('Plugin permission sync failed', ['exception' => $e->getMessage()]);
        }
    }

    private function bootPlugin(PluginManifest $manifest): void
    {
        $this->signatures->assertTrusted($manifest);
        if (!SemVer::satisfies($this->coreVersion, $manifest->compatibleCore)) {
            throw new \RuntimeException('compatibleCore mismatch for ' . $manifest->id);
        }

        $this->registerAutoload($manifest);
        $this->migrator->migrate($manifest);
        $this->translator->addPath($manifest->directory . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang');
        $viewsDir = $manifest->directory . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
        if (is_dir($viewsDir) && $this->container->has(ViewRenderer::class)) {
            $this->container->get(ViewRenderer::class)->addPath($viewsDir);
        }
        try {
            $published = $this->assets->publish($manifest);
            if ($published !== '') {
                $this->assetRegistry->set($manifest->id, $published);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Plugin asset publish failed', [
                'plugin' => $manifest->id,
                'exception' => $e->getMessage(),
            ]);
        }

        $providerClass = $manifest->providerClass;
        $this->loadPluginClass($manifest, $providerClass);
        if (!class_exists($providerClass, false)) {
            throw new \RuntimeException('Provider fehlt: ' . $providerClass);
        }
        $provider = new $providerClass();
        if (!$provider instanceof PluginServiceProvider) {
            throw new \RuntimeException('Provider muss PluginServiceProvider implementieren: ' . $providerClass);
        }

        $kernel = $this->kernel->forPlugin($manifest->id);
        if ($manifest->permissions !== []) {
            $roles = $manifest->permissionRoles !== []
                ? $manifest->permissionRoles
                : ['admin', 'editor'];
            $kernel->registerPermissions($manifest->permissions, $roles);
        }

        $provider->register($this->container);
        $provider->boot($kernel, $this->container);
    }

    /**
     * Prefer the on-disk plugin tree over a possibly stale Composer classmap
     * (e.g. after renaming plugins/{vendor}/…).
     */
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

    /**
     * Load a plugin class from its package directory without consulting Composer.
     * Avoids PHP warnings when Composer still maps the namespace to a missing path.
     */
    private function loadPluginClass(PluginManifest $manifest, string $class): void
    {
        if (class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false)) {
            return;
        }

        $prefix = $manifest->autoloadNamespace;
        if (!str_starts_with($class, $prefix)) {
            throw new \RuntimeException('Klasse liegt außerhalb des Plugin-Autoload-Namespace: ' . $class);
        }

        $relative = substr($class, strlen($prefix));
        $file = $manifest->directory . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
            . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Provider-Datei fehlt: ' . $file);
        }

        require_once $file;
    }
}
