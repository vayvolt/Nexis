<?php

declare(strict_types=1);

/**
 * Export site content package (pages, media, menus, …) as ZIP.
 *
 * Usage:
 *   php bin/export-site.php
 *   php bin/export-site.php --out=/path/to/site-export.zip
 *
 * Exit: 0 = ok, 1 = error.
 */

use Nexis\Kernel\Bootstrap;
use Nexis\Site\SiteExporter;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);
$out = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $out = substr($arg, 6);
    }
}

try {
    $app = Bootstrap::boot($root);
    $path = $app->container->get(SiteExporter::class)->exportZip($out);
    fwrite(STDOUT, "Content-Paket erstellt: {$path}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'export-site failed: ' . $e->getMessage() . "\n");
    exit(1);
}
