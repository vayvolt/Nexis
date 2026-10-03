<?php

declare(strict_types=1);

namespace Nexis\Update;

use Nexis\Infrastructure\Backup\LogicalBackup;
use Nexis\Infrastructure\Database\Migrator;
use Nexis\Kernel\Config;
use Nexis\Kernel\Nexis;
use Nexis\Plugin\MarketplaceClient;
use Nexis\Plugin\SemVer;
use Nexis\Site\MaintenanceMode;
use Nexis\Site\SiteId;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Manual in-app CMS core upgrade from directory ZIP
 * (DB backup + code snapshot → apply → migrate; on failure restore code + DB).
 */
final class CmsPackageUpdater
{
    /** @var list<string> */
    private const REPLACE_DIRS = ['src', 'config', 'resources', 'database', 'vendor'];

    /** @var list<string> */
    private const REPLACE_FILES = [
        'index.php',
        '.htaccess',
        'LICENSE',
        '.env.example',
        'composer.json',
        'composer.lock',
        'RELEASE.txt',
    ];

    /** @var list<string> */
    private const REPLACE_BIN = ['migrate.php', 'queue-work.php', '.htaccess'];

    /** @var list<string> */
    private const FIRST_PARTY_PLUGINS = ['blog', 'catalog', 'consent', 'forms', 'redirects'];

    /** @var list<string> */
    private const FIRST_PARTY_THEMES = ['nexis', 'atelier', 'editorial'];

    public function __construct(
        private Config $config,
        private MarketplaceClient $marketplace,
        private LogicalBackup $backup,
        private Migrator $migrator,
        private MaintenanceMode $maintenance,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array{latest: string, phpRequirement?: string, downloadUrl: string, updateAvailable?: bool} $cms
     * @return array{from: string, to: string, backupStamp: string}
     */
    public function upgradeFromCatalog(array $cms, SiteId $siteId): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP extension zip is required.');
        }

        $to = trim((string) ($cms['latest'] ?? ''));
        $url = trim((string) ($cms['downloadUrl'] ?? ''));
        $phpReq = trim((string) ($cms['phpRequirement'] ?? '>=8.4'));
        $from = Nexis::VERSION;

        if ($to === '' || $url === '') {
            throw new RuntimeException('No CMS package URL from catalog.');
        }
        if (version_compare(ltrim($to, 'vV'), ltrim($from, 'vV'), '==')) {
            throw new RuntimeException('Already on ' . $from . '.');
        }
        $isDowngrade = version_compare(ltrim($to, 'vV'), ltrim($from, 'vV'), '<');
        if ($phpReq !== '' && !self::phpSatisfies($phpReq)) {
            throw new RuntimeException('PHP ' . PHP_VERSION . ' does not meet requirement ' . $phpReq . '.');
        }

        $root = rtrim($this->config->rootPath, '\\/');
        $stagingRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
        $this->ensureDir($stagingRoot);

        $db = [
            'host' => (string) $this->config->get('db.host'),
            'port' => (string) $this->config->get('db.port'),
            'database' => (string) $this->config->get('db.database'),
            'username' => (string) $this->config->get('db.username'),
            'password' => (string) $this->config->get('db.password'),
        ];

        $hadMaintenance = $this->maintenance->isEnabled($siteId);
        if (!$hadMaintenance) {
            $this->maintenance->setEnabled($siteId, true);
        }

        $backupStamp = null;
        $codeSnapshotDir = null;
        $filesApplied = false;
        $zipPath = $stagingRoot . DIRECTORY_SEPARATOR . 'cms-upgrade-' . bin2hex(random_bytes(6)) . '.zip';
        $extractDir = $stagingRoot . DIRECTORY_SEPARATOR . 'cms-extract-' . bin2hex(random_bytes(6));

        try {
            $created = $this->backup->create($db, (string) $this->config->get('app.url', ''));
            $backupStamp = $created['stamp'];

            $this->marketplace->downloadUrlToFile($url, $zipPath);
            $this->ensureDir($extractDir);
            $this->extractZip($zipPath, $extractDir);
            $packageRoot = $this->locatePackageRoot($extractDir);
            $this->assertPackageLooksLikeCms($packageRoot);

            $codeSnapshotDir = $stagingRoot . DIRECTORY_SEPARATOR . 'cms-code-' . bin2hex(random_bytes(6));
            $this->snapshotCode($root, $codeSnapshotDir);

            $this->applyPackage($packageRoot, $root);
            $filesApplied = true;

            // Forward migrations only. Downgrade keeps the current DB schema (additive).
            if (!$isDowngrade) {
                $this->migrator->migrate();
            }
            $this->marketplace->clearUpdateCheckCache();
            $this->clearPageCache($root);

            if (function_exists('opcache_reset')) {
                @opcache_reset();
            }

            if (!$hadMaintenance) {
                $this->maintenance->setEnabled($siteId, false);
            }

            return ['from' => $from, 'to' => $to, 'backupStamp' => $backupStamp];
        } catch (Throwable $e) {
            $this->logger?->error('CMS upgrade failed', [
                'exception' => $e->getMessage(),
                'from' => $from,
                'to' => $to,
                'backup' => $backupStamp,
                'filesApplied' => $filesApplied,
            ]);

            $codeRestored = false;
            $dbRestored = false;
            $rollbackNotes = [];

            if ($filesApplied && is_string($codeSnapshotDir) && is_dir($codeSnapshotDir)) {
                try {
                    $this->restoreCode($codeSnapshotDir, $root);
                    $codeRestored = true;
                    $rollbackNotes[] = 'code restored';
                    if (function_exists('opcache_reset')) {
                        @opcache_reset();
                    }
                } catch (Throwable $codeError) {
                    $rollbackNotes[] = 'code restore failed: ' . $codeError->getMessage();
                }
            }

            if (is_string($backupStamp) && $backupStamp !== '') {
                try {
                    $this->backup->restore($db, $backupStamp, true);
                    $dbRestored = true;
                    $rollbackNotes[] = 'database restored from ' . $backupStamp;
                } catch (Throwable $restoreError) {
                    $rollbackNotes[] = 'database restore failed: ' . $restoreError->getMessage();
                }
            }

            $rollbackOk = (!$filesApplied || $codeRestored)
                && (is_string($backupStamp) && $backupStamp !== '' ? $dbRestored : true);

            if ($rollbackOk && !$hadMaintenance) {
                $this->maintenance->setEnabled($siteId, false);
            }

            $suffix = $rollbackNotes !== [] ? ' — ' . implode('; ', $rollbackNotes) . '.' : '';
            if (!$rollbackOk) {
                $suffix .= ' Maintenance mode stays on until the installation is repaired.';
            }

            throw new RuntimeException(
                'Upgrade failed: ' . $e->getMessage() . $suffix,
                0,
                $e,
            );
        } finally {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            if (is_dir($extractDir)) {
                $this->deleteTree($extractDir);
            }
            if (is_string($codeSnapshotDir) && is_dir($codeSnapshotDir)) {
                $this->deleteTree($codeSnapshotDir);
            }
        }
    }

    /**
     * Copy paths that upgrade will replace into a staging snapshot.
     */
    private function snapshotCode(string $installRoot, string $snapshotRoot): void
    {
        $this->ensureDir($snapshotRoot);
        foreach (self::REPLACE_DIRS as $dir) {
            $from = $installRoot . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($from)) {
                continue;
            }
            $this->copyTree($from, $snapshotRoot . DIRECTORY_SEPARATOR . $dir);
        }
        foreach (self::REPLACE_FILES as $file) {
            $from = $installRoot . DIRECTORY_SEPARATOR . $file;
            if (!is_file($from)) {
                continue;
            }
            $to = $snapshotRoot . DIRECTORY_SEPARATOR . $file;
            $this->ensureDir(dirname($to));
            if (!@copy($from, $to)) {
                throw new RuntimeException('Cannot snapshot ' . $file);
            }
        }
        $binFrom = $installRoot . DIRECTORY_SEPARATOR . 'bin';
        if (is_dir($binFrom)) {
            $binTo = $snapshotRoot . DIRECTORY_SEPARATOR . 'bin';
            $this->ensureDir($binTo);
            foreach (self::REPLACE_BIN as $file) {
                $from = $binFrom . DIRECTORY_SEPARATOR . $file;
                if (!is_file($from)) {
                    continue;
                }
                if (!@copy($from, $binTo . DIRECTORY_SEPARATOR . $file)) {
                    throw new RuntimeException('Cannot snapshot bin/' . $file);
                }
            }
        }
        foreach (self::FIRST_PARTY_PLUGINS as $name) {
            $from = $installRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($from)) {
                continue;
            }
            $this->copyTree(
                $from,
                $snapshotRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name,
            );
        }
        foreach (self::FIRST_PARTY_THEMES as $name) {
            $from = $installRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($from)) {
                continue;
            }
            $this->copyTree(
                $from,
                $snapshotRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name,
            );
        }
        $assetsFrom = $installRoot . DIRECTORY_SEPARATOR . 'assets';
        if (is_dir($assetsFrom)) {
            $this->snapshotReplaceableAssets($assetsFrom, $snapshotRoot . DIRECTORY_SEPARATOR . 'assets');
        }
        // Marker so empty installs still have a recognizable snapshot dir.
        file_put_contents($snapshotRoot . DIRECTORY_SEPARATOR . '.nexis-code-snapshot', gmdate('c'));
    }

    private function snapshotReplaceableAssets(string $assetsFrom, string $assetsTo): void
    {
        $this->ensureDir($assetsTo);
        $items = scandir($assetsFrom);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'plugins' || $item === 'themes') {
                continue;
            }
            $from = $assetsFrom . DIRECTORY_SEPARATOR . $item;
            $to = $assetsTo . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from) && !is_link($from)) {
                $this->copyTree($from, $to);
            } elseif (is_file($from)) {
                if (!@copy($from, $to)) {
                    throw new RuntimeException('Cannot snapshot assets/' . $item);
                }
            }
        }
    }

    /**
     * Put snapshotted code paths back (same targets as applyPackage, without package assets merge).
     */
    private function restoreCode(string $snapshotRoot, string $installRoot): void
    {
        if (!is_file($snapshotRoot . DIRECTORY_SEPARATOR . '.nexis-code-snapshot')) {
            throw new RuntimeException('Code snapshot missing or incomplete.');
        }
        foreach (self::REPLACE_DIRS as $dir) {
            $from = $snapshotRoot . DIRECTORY_SEPARATOR . $dir;
            $to = $installRoot . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($from)) {
                continue;
            }
            if (is_dir($to)) {
                $this->deleteTree($to);
            }
            $this->copyTree($from, $to);
        }
        foreach (self::REPLACE_FILES as $file) {
            $from = $snapshotRoot . DIRECTORY_SEPARATOR . $file;
            $to = $installRoot . DIRECTORY_SEPARATOR . $file;
            if (!is_file($from)) {
                if (is_file($to) && $file === 'RELEASE.txt') {
                    @unlink($to);
                }
                continue;
            }
            $this->ensureDir(dirname($to));
            if (!@copy($from, $to)) {
                throw new RuntimeException('Cannot restore ' . $file);
            }
        }
        $binFrom = $snapshotRoot . DIRECTORY_SEPARATOR . 'bin';
        $binTo = $installRoot . DIRECTORY_SEPARATOR . 'bin';
        if (is_dir($binFrom)) {
            $this->ensureDir($binTo);
            foreach (self::REPLACE_BIN as $file) {
                $from = $binFrom . DIRECTORY_SEPARATOR . $file;
                if (!is_file($from)) {
                    continue;
                }
                if (!@copy($from, $binTo . DIRECTORY_SEPARATOR . $file)) {
                    throw new RuntimeException('Cannot restore bin/' . $file);
                }
            }
        }
        foreach (self::FIRST_PARTY_PLUGINS as $name) {
            $from = $snapshotRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            $to = $installRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($from)) {
                continue;
            }
            if (is_dir($to)) {
                $this->deleteTree($to);
            }
            $this->copyTree($from, $to);
        }
        foreach (self::FIRST_PARTY_THEMES as $name) {
            $from = $snapshotRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            $to = $installRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($from)) {
                continue;
            }
            if (is_dir($to)) {
                $this->deleteTree($to);
            }
            $this->copyTree($from, $to);
        }
        $assetsSnap = $snapshotRoot . DIRECTORY_SEPARATOR . 'assets';
        $assetsLive = $installRoot . DIRECTORY_SEPARATOR . 'assets';
        if (is_dir($assetsSnap)) {
            $this->restoreReplaceableAssets($assetsSnap, $assetsLive);
        }
    }

    private function restoreReplaceableAssets(string $assetsSnap, string $assetsLive): void
    {
        $this->ensureDir($assetsLive);
        $items = scandir($assetsSnap);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $assetsSnap . DIRECTORY_SEPARATOR . $item;
            $to = $assetsLive . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from) && !is_link($from)) {
                if (is_dir($to)) {
                    $this->deleteTree($to);
                }
                $this->copyTree($from, $to);
            } elseif (is_file($from)) {
                if (!@copy($from, $to)) {
                    throw new RuntimeException('Cannot restore assets/' . $item);
                }
            }
        }
    }

    private static function phpSatisfies(string $constraint): bool
    {
        $constraint = trim($constraint);
        $version = PHP_VERSION;
        if (str_starts_with($constraint, '>=')) {
            $base = trim(substr($constraint, 2));

            return version_compare($version, $base, '>=');
        }
        if (str_starts_with($constraint, '>')) {
            $base = trim(substr($constraint, 1));

            return version_compare($version, $base, '>');
        }

        return SemVer::satisfies($version, $constraint);
    }

    private function applyPackage(string $packageRoot, string $installRoot): void
    {
        foreach (self::REPLACE_DIRS as $dir) {
            $from = $packageRoot . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($from)) {
                continue;
            }
            $to = $installRoot . DIRECTORY_SEPARATOR . $dir;
            if (is_dir($to)) {
                $this->deleteTree($to);
            }
            $this->copyTree($from, $to);
        }

        foreach (self::REPLACE_FILES as $file) {
            $from = $packageRoot . DIRECTORY_SEPARATOR . $file;
            if (!is_file($from)) {
                continue;
            }
            $to = $installRoot . DIRECTORY_SEPARATOR . $file;
            $this->ensureDir(dirname($to));
            if (!@copy($from, $to)) {
                throw new RuntimeException('Cannot write ' . $file);
            }
        }

        $binFrom = $packageRoot . DIRECTORY_SEPARATOR . 'bin';
        $binTo = $installRoot . DIRECTORY_SEPARATOR . 'bin';
        if (is_dir($binFrom)) {
            $this->ensureDir($binTo);
            foreach (self::REPLACE_BIN as $file) {
                $from = $binFrom . DIRECTORY_SEPARATOR . $file;
                if (!is_file($from)) {
                    continue;
                }
                if (!@copy($from, $binTo . DIRECTORY_SEPARATOR . $file)) {
                    throw new RuntimeException('Cannot write bin/' . $file);
                }
            }
        }

        foreach (self::FIRST_PARTY_PLUGINS as $name) {
            $from = $packageRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($from)) {
                continue;
            }
            $to = $installRoot . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (is_dir($to)) {
                $this->deleteTree($to);
            }
            $this->copyTree($from, $to);
        }

        foreach (self::FIRST_PARTY_THEMES as $name) {
            $from = $packageRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (!is_dir($from)) {
                continue;
            }
            $to = $installRoot . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . $name;
            if (is_dir($to)) {
                $this->deleteTree($to);
            }
            $this->copyTree($from, $to);
        }

        $assetsFrom = $packageRoot . DIRECTORY_SEPARATOR . 'assets';
        $assetsTo = $installRoot . DIRECTORY_SEPARATOR . 'assets';
        if (is_dir($assetsFrom)) {
            $this->mergeAssets($assetsFrom, $assetsTo);
        }
    }

    private function mergeAssets(string $from, string $to): void
    {
        $this->ensureDir($to);
        $items = scandir($from);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            // Keep published plugin/theme assets; only refresh scaffolds from package.
            if ($item === 'plugins' || $item === 'themes') {
                $src = $from . DIRECTORY_SEPARATOR . $item;
                $dst = $to . DIRECTORY_SEPARATOR . $item;
                $this->ensureDir($dst);
                $keep = $src . DIRECTORY_SEPARATOR . '.gitkeep';
                if (is_file($keep) && !is_file($dst . DIRECTORY_SEPARATOR . '.gitkeep')) {
                    @copy($keep, $dst . DIRECTORY_SEPARATOR . '.gitkeep');
                }
                continue;
            }
            $src = $from . DIRECTORY_SEPARATOR . $item;
            $dst = $to . DIRECTORY_SEPARATOR . $item;
            if (is_dir($src) && !is_link($src)) {
                if (is_dir($dst)) {
                    $this->deleteTree($dst);
                }
                $this->copyTree($src, $dst);
            } elseif (is_file($src)) {
                if (!@copy($src, $dst)) {
                    throw new RuntimeException('Cannot write assets/' . $item);
                }
            }
        }
    }

    private function locatePackageRoot(string $extractDir): string
    {
        $extractDir = rtrim($extractDir, '\\/');
        if ($this->looksLikeCmsRoot($extractDir)) {
            return $extractDir;
        }
        $dirs = glob($extractDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];
        $candidates = [];
        foreach ($dirs as $dir) {
            if ($this->looksLikeCmsRoot($dir)) {
                $candidates[] = $dir;
            }
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        throw new RuntimeException('CMS package root not found in ZIP (expected nexis/ with index.php + src/).');
    }

    private function looksLikeCmsRoot(string $dir): bool
    {
        return is_file($dir . DIRECTORY_SEPARATOR . 'index.php')
            && is_dir($dir . DIRECTORY_SEPARATOR . 'src');
    }

    private function assertPackageLooksLikeCms(string $root): void
    {
        foreach (['src', 'config', 'resources'] as $dir) {
            if (!is_dir($root . DIRECTORY_SEPARATOR . $dir)) {
                throw new RuntimeException('Incomplete CMS package (missing ' . $dir . '/).');
            }
        }
        $versionFile = $root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Kernel' . DIRECTORY_SEPARATOR . 'Nexis.php';
        if (!is_file($versionFile)) {
            throw new RuntimeException('Incomplete CMS package (missing Nexis.php).');
        }
    }

    private function extractZip(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open CMS ZIP.');
        }
        $destinationReal = realpath($destination);
        if ($destinationReal === false) {
            $zip->close();
            throw new RuntimeException('Invalid extract directory.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (!is_string($name) || $name === '') {
                    continue;
                }
                $name = str_replace('\\', '/', $name);
                if (str_contains($name, "\0") || str_starts_with($name, '/')
                    || preg_match('#(^|/)\.\.(/|$)#', $name) === 1) {
                    throw new RuntimeException('ZIP contains unsafe paths.');
                }
                $target = $destinationReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                $normTarget = $this->normalizePath($target);
                $normDest = $this->normalizePath($destinationReal);
                if ($normTarget !== $normDest && !str_starts_with($normTarget, $normDest . DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('ZIP contains unsafe paths.');
                }
                if (str_ends_with($name, '/')) {
                    $this->ensureDir($target);
                    continue;
                }
                $this->ensureDir(dirname($target));
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException('Unreadable ZIP entry: ' . $name);
                }
                $out = fopen($target, 'wb');
                if ($out === false) {
                    fclose($stream);
                    throw new RuntimeException('Cannot write extract file.');
                }
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);
            }
        } finally {
            $zip->close();
        }
    }

    private function clearPageCache(string $root): void
    {
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'pages';
        if (is_dir($dir)) {
            $this->deleteTree($dir);
            $this->ensureDir($dir);
        }
    }

    private function ensureDir(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create directory: ' . $path);
        }
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $resolved = realpath($path);

        return $resolved !== false ? $resolved : $path;
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
            throw new RuntimeException('Cannot read ' . $source);
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $source . DIRECTORY_SEPARATOR . $item;
            $to = $destination . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from) && !is_link($from)) {
                $this->copyTree($from, $to);
            } elseif (!@copy($from, $to)) {
                throw new RuntimeException('Cannot copy ' . $item);
            }
        }
    }
}
