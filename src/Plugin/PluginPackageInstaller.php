<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use InvalidArgumentException;
use Nexis\Kernel\Nexis;
use Nexis\Site\SiteId;
use RuntimeException;
use ZipArchive;

/**
 * Installs a plugin ZIP into plugins/{vendor}/{name} via staging + atomic move.
 */
final class PluginPackageInstaller
{
    public function __construct(
        private string $pluginsRoot,
        private string $stagingRoot,
        private ManifestLoader $loader,
        private PluginSignatureVerifier $signatures,
        private PluginCatalog $catalog,
        private string $coreVersion = Nexis::VERSION,
    ) {
    }

    /**
     * @return array{manifest: PluginManifest, overwritten: bool}
     */
    public function installFromZip(string $zipPath, SiteId $siteId, bool $overwrite = false): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP-Extension zip fehlt.');
        }
        if (!is_file($zipPath)) {
            throw new InvalidArgumentException('ZIP-Datei nicht gefunden.');
        }

        $this->ensureDir($this->stagingRoot);
        $this->ensureDir($this->pluginsRoot);

        $stageId = bin2hex(random_bytes(8));
        $staging = rtrim($this->stagingRoot, '\\/') . DIRECTORY_SEPARATOR . 'plugin-' . $stageId;
        $this->ensureDir($staging);

        try {
            $this->extractZip($zipPath, $staging);
            $pluginDir = $this->locatePluginRoot($staging);
            $manifest = $this->loader->load($pluginDir);
            $this->assertValidId($manifest->id);
            $this->signatures->assertTrusted($manifest);

            if (!SemVer::satisfies($this->coreVersion, $manifest->compatibleCore)) {
                throw new RuntimeException(
                    'Plugin ist nicht mit Core ' . $this->coreVersion . ' kompatibel (' . $manifest->compatibleCore . ').',
                );
            }

            [$vendor, $name] = explode('/', $manifest->id, 2);
            $target = rtrim($this->pluginsRoot, '\\/')
                . DIRECTORY_SEPARATOR . $vendor
                . DIRECTORY_SEPARATOR . $name;

            $overwritten = is_dir($target);
            if ($overwritten && !$overwrite) {
                throw new RuntimeException('Plugin existiert bereits: ' . $manifest->id . ' (Overwrite bestätigen).');
            }

            $vendorDir = dirname($target);
            $this->ensureDir($vendorDir);

            if ($overwritten) {
                $this->deleteTree($target);
            }

            if (!@rename($pluginDir, $target)) {
                $this->copyTree($pluginDir, $target);
                $this->deleteTree($pluginDir);
            }

            $installed = $this->loader->load($target);
            // Partial upsert only — full sync+prune would delete every other installation.
            $this->catalog->sync([$installed], false);
            $this->catalog->ensureInstallation($siteId, $installed->id, PluginInstallStatus::Installed);

            return ['manifest' => $installed, 'overwritten' => $overwritten];
        } finally {
            if (is_dir($staging)) {
                $this->deleteTree($staging);
            }
        }
    }

    private function extractZip(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);
        if ($opened !== true) {
            throw new InvalidArgumentException('ZIP konnte nicht geöffnet werden.');
        }

        $destinationReal = realpath($destination);
        if ($destinationReal === false) {
            $zip->close();
            throw new RuntimeException('Staging-Verzeichnis ungültig.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $name = str_replace('\\', '/', $name);
                if (str_contains($name, "\0")) {
                    throw new RuntimeException('Ungültiger ZIP-Eintrag.');
                }
                if (str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
                    throw new RuntimeException('ZIP enthält unsichere Pfade (Zip-Slip).');
                }

                $target = $destinationReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                $targetNormalized = $this->normalizePath($target);
                if (!str_starts_with($targetNormalized, $this->normalizePath($destinationReal) . DIRECTORY_SEPARATOR)
                    && $targetNormalized !== $this->normalizePath($destinationReal)) {
                    throw new RuntimeException('ZIP enthält unsichere Pfade (Zip-Slip).');
                }

                if (str_ends_with($name, '/')) {
                    $this->ensureDir($target);
                    continue;
                }

                $this->ensureDir(dirname($target));
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('ZIP-Eintrag unlesbar: ' . $name);
                }
                $out = fopen($target, 'wb');
                if ($out === false) {
                    fclose($stream);
                    throw new RuntimeException('Staging-Datei nicht schreibbar.');
                }
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    private function locatePluginRoot(string $staging): string
    {
        $direct = rtrim($staging, '\\/') . DIRECTORY_SEPARATOR . 'plugin.json';
        if (is_file($direct)) {
            return rtrim($staging, '\\/');
        }

        $dirs = glob(rtrim($staging, '\\/') . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $candidates = [];
        foreach ($dirs as $dir) {
            if (is_file($dir . DIRECTORY_SEPARATOR . 'plugin.json')) {
                $candidates[] = $dir;
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        if ($candidates === []) {
            throw new InvalidArgumentException('Keine plugin.json im ZIP gefunden.');
        }

        throw new InvalidArgumentException('ZIP enthält mehrere Plugin-Verzeichnisse.');
    }

    private function assertValidId(string $id): void
    {
        if (preg_match('#^[a-z0-9][a-z0-9_-]*/[a-z0-9][a-z0-9_-]*$#', $id) !== 1) {
            throw new InvalidArgumentException('Ungültige Plugin-ID: ' . $id);
        }
    }

    private function ensureDir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Verzeichnis nicht anlegbar: ' . $path);
        }
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $resolved = realpath($path);
        if ($resolved !== false) {
            return $resolved;
        }

        return $path;
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full) && !is_link($full)) {
                $this->deleteTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }

    private function copyTree(string $source, string $destination): void
    {
        $this->ensureDir($destination);
        $items = scandir($source);
        if ($items === false) {
            throw new RuntimeException('Quellverzeichnis unlesbar.');
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from) && !is_link($from)) {
                $this->copyTree($from, $to);
            } else {
                if (!@copy($from, $to)) {
                    throw new RuntimeException('Datei konnte nicht kopiert werden: ' . $item);
                }
            }
        }
    }
}
