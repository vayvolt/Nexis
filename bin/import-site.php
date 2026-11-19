<?php

declare(strict_types=1);

/**
 * Import a Nexis content ZIP into the installed site.
 *
 * Usage:
 *   php bin/import-site.php path/to/site-export.zip --yes
 *   php bin/import-site.php path/to/site-export.zip --dry-run
 *
 * --dry-run  report only, no writes
 * --yes      required for a real import (safety)
 *
 * Exit: 0 = ok, 1 = error / missing --yes.
 */

use Nexis\Auth\UserId;
use Nexis\Kernel\Bootstrap;
use Nexis\Site\SiteImporter;
use Nexis\Site\SiteRepository;
use PDO;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);
$dryRun = in_array('--dry-run', $args, true);
$yes = in_array('--yes', $args, true);
$zipPath = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) {
        continue;
    }
    $zipPath = $arg;
    break;
}

if ($zipPath === null || $zipPath === '') {
    fwrite(STDERR, "Usage: php bin/import-site.php <zip> [--dry-run|--yes]\n");
    exit(1);
}
if (!is_file($zipPath)) {
    fwrite(STDERR, "ZIP not found: {$zipPath}\n");
    exit(1);
}
if (!$dryRun && !$yes) {
    fwrite(STDERR, "Refusing real import without --yes (or use --dry-run).\n");
    exit(1);
}

try {
    $app = Bootstrap::boot($root);
    $site = $app->container->get(SiteRepository::class)->installed();
    if ($site === null) {
        fwrite(STDERR, "No installed site.\n");
        exit(1);
    }
    $pdo = $app->container->get(PDO::class);
    $actorId = $pdo->query(
        'SELECT id FROM users WHERE is_platform_admin = 1 AND deleted_at IS NULL ORDER BY created_at ASC LIMIT 1',
    )->fetchColumn();
    if (!is_string($actorId) || $actorId === '') {
        fwrite(STDERR, "No platform admin user for import actor.\n");
        exit(1);
    }
    $actor = new UserId($actorId);
    $result = $app->container->get(SiteImporter::class)->importZip($zipPath, $actor, '', $dryRun);
    $stats = $result->toArray();
    $label = $dryRun ? 'Dry-run' : 'Import';
    fwrite(
        STDOUT,
        "{$label}: pages={$stats['pages']} (updated={$stats['pages_updated']}), "
        . "media={$stats['media']} (updated={$stats['media_updated']}, skipped={$stats['media_skipped']}), "
        . "menus={$stats['menus']}, redirects={$stats['redirects']}, settings={$stats['settings']}, "
        . "published={$stats['published']}\n",
    );
    foreach ($stats['warnings'] as $warning) {
        fwrite(STDOUT, "Warning: {$warning}\n");
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'import-site failed: ' . $e->getMessage() . "\n");
    exit(1);
}
