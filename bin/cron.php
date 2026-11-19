<?php

declare(strict_types=1);

/**
 * Unified Nexis cron entry point (one crontab line).
 *
 * Runs per invocation:
 * - webhook / mail / plugin queue jobs
 * - due scheduled publish + unpublish
 * - audit / idempotency / forms retention purge
 * - due backup planner (Admin → Backups)
 *
 * Usage:
 *   php bin/cron.php
 *   composer cron
 *
 * Web hosts without CLI cron: root cron.php + CRON_SECRET (see docs/ops/cron.md).
 *
 * Crontab example: docs/ops/crontab.example
 *
 * Exit codes: 0 = ok, 1 = fatal/lock/error, 75 = already running (EX_TEMPFAIL).
 */

use Nexis\Infrastructure\Cron\CronRunner;
use Nexis\Kernel\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = Bootstrap::boot(dirname(__DIR__));
$result = $app->container->get(CronRunner::class)->run();

foreach ($result->lines as $line) {
    fwrite(STDOUT, $line . "\n");
}
if ($result->error !== null) {
    fwrite(STDERR, $result->error . "\n");
}

if ($result->busy) {
    exit(75);
}
exit($result->ok ? 0 : 1);
