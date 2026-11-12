<?php

declare(strict_types=1);

/**
 * Build a distributable Nexis CMS ZIP for the Directory download.
 *
 * Usage:
 *   php bin/pack-release.php
 *   php bin/pack-release.php --no-vendor
 *   php bin/pack-release.php --no-webseite
 *   php bin/pack-release.php --version=0.5.0
 *   php bin/pack-release.php --out=D:\path\nexis-0.5.0.zip
 *
 * Default: production Composer deps, ZIP root folder "nexis/",
 * copy to ../nexis-webseite/storage/cms/{version}.zip when that tree exists.
 *
 * Ship list / forbidden packages: bin/pack-release-manifest.php (allowlist).
 * New plugins/themes do not ship until listed there.
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require __DIR__ . '/pack-release-lib.php';

/** @var array{plugins: list<string>, themes: list<string>, forbidden: list<string>} $packManifest */
$packManifest = require __DIR__ . '/pack-release-manifest.php';

use Nexis\Kernel\Nexis;

$args = array_slice($argv, 1);
$withVendor = !in_array('--no-vendor', $args, true);
$copyWebseite = !in_array('--no-webseite', $args, true);
$outOverride = null;
$versionOverride = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $outOverride = substr($arg, 6);
    }
    if (str_starts_with($arg, '--version=')) {
        $versionOverride = substr($arg, 10);
    }
}

$version = $versionOverride !== null && $versionOverride !== ''
    ? ltrim($versionOverride, 'vV')
    : Nexis::VERSION;

$tmpRoot = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
    fwrite(STDERR, "Cannot create storage/tmp\n");
    exit(1);
}

$staging = $tmpRoot . DIRECTORY_SEPARATOR . 'pack-release-' . bin2hex(random_bytes(4));
$zipOut = $outOverride !== null && $outOverride !== ''
    ? $outOverride
    : $tmpRoot . DIRECTORY_SEPARATOR . 'nexis-' . $version . '.zip';

$restoredDev = false;
try {
    if (is_dir($staging)) {
        removeTree($staging);
    }
    mkdir($staging . DIRECTORY_SEPARATOR . 'nexis', 0775, true);

    if ($withVendor) {
        runComposerInstall($root, false);
        $restoredDev = true;
    }

    $copied = 0;
    foreach (packPaths($root, $packManifest) as $rel) {
        $from = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $to = $staging . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!is_file($from) && !is_dir($from)) {
            continue;
        }
        $dir = dirname($to);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('mkdir failed: ' . $dir);
        }
        if (is_dir($from)) {
            copyTree($from, $to);
        } else {
            if (!copy($from, $to)) {
                throw new RuntimeException('copy failed: ' . $rel);
            }
        }
        $copied++;
    }

    assertPackStagingClean($staging . DIRECTORY_SEPARATOR . 'nexis', $packManifest['forbidden']);

    $changelogExcerpt = changelogSection($root . DIRECTORY_SEPARATOR . 'CHANGELOG.md', $version);
    $release = "Nexis {$version}\n"
        . 'Built: ' . gmdate('c') . "\n"
        . 'License: ' . Nexis::LICENSE . "\n\n"
        . "Install:\n"
        . "1. ZIP entpacken, Document Root = Ordner nexis/\n"
        . "2. Falls ohne vendor: composer install --no-dev\n"
        . "3. Browser: /install\n"
        . "   oder CLI: .env aus .env.example, dann php bin/migrate.php\n\n"
        . "Betrieb: php bin/cron.php (Cron empfohlen, siehe docs/ops/crontab.example im Repo)\n\n"
        . "Changelog:\n"
        . $changelogExcerpt;
    file_put_contents($staging . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . 'RELEASE.txt', $release);

    if (is_file($zipOut)) {
        unlink($zipOut);
    }
    zipDirectory($staging, $zipOut);
    fwrite(STDOUT, "Packed {$copied} path(s) → {$zipOut}\n");

    if ($copyWebseite) {
        $portalCms = dirname($root) . DIRECTORY_SEPARATOR . 'nexis-webseite'
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cms';
        if (is_dir($portalCms)) {
            $dest = $portalCms . DIRECTORY_SEPARATOR . $version . '.zip';
            if (!copy($zipOut, $dest)) {
                fwrite(STDERR, "Warn: could not copy to {$dest}\n");
            } else {
                fwrite(STDOUT, "Copied → {$dest}\n");
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    if (!empty($restoredDev)) {
        try {
            runComposerInstall($root, true);
        } catch (Throwable) {
            // leave workspace as-is; operator can run composer install
        }
    }
    exit(1);
} finally {
    if (is_dir($staging)) {
        removeTree($staging);
    }
    if (!empty($restoredDev)) {
        try {
            runComposerInstall($root, true);
            fwrite(STDOUT, "Restored require-dev for local workspace.\n");
        } catch (Throwable $e) {
            fwrite(STDERR, "Warn: could not restore require-dev: " . $e->getMessage() . "\n");
            fwrite(STDERR, "Run: composer install\n");
        }
    }
}
