<?php

declare(strict_types=1);

/**
 * Background CMS core upgrade worker (started from Admin → About).
 *
 * Usage: php bin/cms-upgrade.php <jobId>
 */
use Nexis\Kernel\Bootstrap;
use Nexis\Site\SiteId;
use Nexis\Update\CmsPackageUpdater;
use Nexis\Update\CmsUpgradeProgress;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$jobId = trim((string) (($argv ?? [])[1] ?? ''));
if ($jobId === '') {
    fwrite(STDERR, "Usage: php bin/cms-upgrade.php <jobId>\n");
    exit(1);
}

@set_time_limit(0);
@ini_set('max_execution_time', '0');
ignore_user_abort(true);

$app = Bootstrap::boot($root);
$progress = $app->container->get(CmsUpgradeProgress::class);
$claimed = $progress->claim($jobId);
if ($claimed === null) {
    fwrite(STDOUT, "Nothing to claim for {$jobId}\n");
    exit(0);
}

$cms = $claimed['cms'];
$siteId = new SiteId($claimed['siteId']);
$updater = $app->container->get(CmsPackageUpdater::class);

try {
    $result = $updater->upgradeFromCatalog(
        $cms,
        $siteId,
        static function (string $step, string $state, string $detail = '') use ($progress): void {
            $progress->setStep($step, $state, $detail);
        },
    );
    $progress->complete($result);
    fwrite(STDOUT, "Upgrade {$result['from']} → {$result['to']} ok (backup {$result['backupStamp']})\n");
    exit(0);
} catch (Throwable $e) {
    $progress->fail($e->getMessage());
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
