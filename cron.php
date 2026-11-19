<?php

declare(strict_types=1);

/**
 * Web cron entry for hosts that only allow HTTP cron (no CLI).
 *
 * Setup:
 *   1. Set CRON_SECRET in .env to a long random string
 *   2. Point the hoster cron at:
 *      https://example.com/cron.php?key=YOUR_SECRET
 *      (or header X-Nexis-Cron-Key: YOUR_SECRET)
 *
 * Disabled when CRON_SECRET is empty. Same work as `php bin/cron.php`.
 */

use Nexis\Infrastructure\Cron\CronRunner;
use Nexis\Kernel\Bootstrap;
use Nexis\Kernel\Config;

require __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$root = __DIR__;
if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(503);
    echo "nexis cron: vendor missing\n";
    exit;
}

try {
    $app = Bootstrap::boot($root);
    $config = $app->container->get(Config::class);
    $secret = trim((string) $config->get('cron.secret', ''));
    if ($secret === '') {
        http_response_code(403);
        echo "nexis cron: disabled (set CRON_SECRET in .env)\n";
        exit;
    }

    $provided = '';
    $header = $_SERVER['HTTP_X_NEXIS_CRON_KEY'] ?? '';
    if (is_string($header) && $header !== '') {
        $provided = $header;
    } elseif (isset($_GET['key']) && is_string($_GET['key'])) {
        $provided = $_GET['key'];
    }

    if ($provided === '' || !hash_equals($secret, $provided)) {
        http_response_code(403);
        echo "nexis cron: forbidden\n";
        exit;
    }

    @set_time_limit(300);
    $result = $app->container->get(CronRunner::class)->run();

    if ($result->busy) {
        http_response_code(409);
    } elseif (!$result->ok) {
        http_response_code(500);
    } else {
        http_response_code(200);
    }

    foreach ($result->lines as $line) {
        echo $line . "\n";
    }
    if ($result->error !== null) {
        echo $result->error . "\n";
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'nexis cron failed: ' . $e->getMessage() . "\n";
}
