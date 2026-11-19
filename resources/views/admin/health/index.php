<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var array{
 *   overall: string,
 *   database: string,
 *   storage: array{status: string, path: string, freeBytes: ?int, totalBytes: ?int, freeLabel: string, totalLabel: string, usedPercent: ?float},
 *   queue: array{status: string, pending: ?int, reserved: ?int, failed: ?int, byQueue: list<array{queue: string, pending: int}>},
 *   php: array{version: string, sapi: string, memoryLimit: string, memoryUsage: string, memoryPeak: string, opcache: bool, extensions: list<string>},
 *   app: array{env: string, debug: bool, version: string, timezone: string}
 * } $health
 */
$health = $health ?? [];
$overall = (string) ($health['overall'] ?? 'degraded');
$db = (string) ($health['database'] ?? 'down');
$storage = $health['storage'] ?? ['status' => 'error', 'path' => 'storage/', 'freeLabel' => '—', 'totalLabel' => '—', 'usedPercent' => null];
$queue = $health['queue'] ?? ['status' => 'error', 'pending' => null, 'reserved' => null, 'failed' => null, 'byQueue' => []];
$php = $health['php'] ?? [];
$app = $health['app'] ?? [];
$publicHealthUrl = (string) ($publicHealthUrl ?? '/health');

$statusBadge = static function (string $status) use ($t): array {
    return match ($status) {
        'ok' => ['class' => 'badge-admin', 'label' => $t('admin.health.status_ok')],
        default => ['class' => 'badge-editor', 'label' => $t('admin.health.status_degraded')],
    };
};
$overallBadge = $statusBadge($overall);
?>
<h1><?php echo $e($t('admin.health.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.health.intro')) ?></p>

<p style="margin:0 0 1rem">
    <span class="badge <?php echo $e($overallBadge['class']) ?>"><?php echo $e($overallBadge['label']) ?></span>
    <span class="muted" style="margin-left:.5rem;font-size:.9rem">
        <?php echo $e($t('admin.health.public_endpoint')) ?>:
        <a href="<?php echo $e($publicHealthUrl) ?>" target="_blank" rel="noopener"><code><?php echo $e($publicHealthUrl) ?></code></a>
    </span>
</p>

<div class="dash-grid">
    <section class="card dash-card">
        <h2><?php echo $e($t('admin.health.queue')) ?></h2>
        <?php $qBadge = $statusBadge((string) ($queue['status'] ?? 'error')); ?>
        <p><span class="badge <?php echo $e($qBadge['class']) ?>"><?php echo $e($qBadge['label']) ?></span></p>
        <dl class="health-dl">
            <div><dt><?php echo $e($t('admin.health.queue_pending')) ?></dt><dd><strong><?php echo $e((string) ($queue['pending'] ?? '—')) ?></strong></dd></div>
            <div><dt><?php echo $e($t('admin.health.queue_reserved')) ?></dt><dd><?php echo $e((string) ($queue['reserved'] ?? '—')) ?></dd></div>
            <div><dt><?php echo $e($t('admin.health.queue_failed')) ?></dt><dd><?php echo $e((string) ($queue['failed'] ?? '—')) ?></dd></div>
        </dl>
        <?php if (($queue['byQueue'] ?? []) !== []): ?>
            <table style="margin-top:.75rem">
                <thead>
                    <tr>
                        <th><?php echo $e($t('admin.health.queue_name')) ?></th>
                        <th style="width:5rem"><?php echo $e($t('admin.health.queue_pending')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue['byQueue'] as $row): ?>
                        <tr>
                            <td><code><?php echo $e($row['queue']) ?></code></td>
                            <td><?php echo $e((string) $row['pending']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted" style="margin-top:.65rem"><?php echo $e($t('admin.health.queue_empty')) ?></p>
        <?php endif; ?>
        <p class="muted" style="margin-top:.85rem;font-size:.85rem"><?php echo $e($t('admin.health.queue_hint')) ?></p>
    </section>

    <section class="card dash-card">
        <h2><?php echo $e($t('admin.health.disk')) ?></h2>
        <?php $sBadge = $statusBadge((string) ($storage['status'] ?? 'error')); ?>
        <p><span class="badge <?php echo $e($sBadge['class']) ?>"><?php echo $e($sBadge['label']) ?></span></p>
        <dl class="health-dl">
            <div><dt><?php echo $e($t('admin.health.disk_path')) ?></dt><dd><code><?php echo $e((string) ($storage['path'] ?? 'storage/')) ?></code></dd></div>
            <div><dt><?php echo $e($t('admin.health.disk_free')) ?></dt><dd><strong><?php echo $e((string) ($storage['freeLabel'] ?? '—')) ?></strong></dd></div>
            <div><dt><?php echo $e($t('admin.health.disk_total')) ?></dt><dd><?php echo $e((string) ($storage['totalLabel'] ?? '—')) ?></dd></div>
            <?php if (isset($storage['usedPercent']) && $storage['usedPercent'] !== null): ?>
                <div><dt><?php echo $e($t('admin.health.disk_used')) ?></dt><dd><?php echo $e((string) $storage['usedPercent']) ?>%</dd></div>
            <?php endif; ?>
        </dl>
    </section>

    <section class="card dash-card">
        <h2><?php echo $e($t('admin.health.php')) ?></h2>
        <dl class="health-dl">
            <div><dt><?php echo $e($t('admin.health.php_version')) ?></dt><dd><strong><?php echo $e((string) ($php['version'] ?? PHP_VERSION)) ?></strong></dd></div>
            <div><dt><?php echo $e($t('admin.health.php_sapi')) ?></dt><dd><?php echo $e((string) ($php['sapi'] ?? PHP_SAPI)) ?></dd></div>
            <div><dt><?php echo $e($t('admin.health.php_memory_limit')) ?></dt><dd><?php echo $e((string) ($php['memoryLimit'] ?? '—')) ?></dd></div>
            <div><dt><?php echo $e($t('admin.health.php_memory_usage')) ?></dt><dd><?php echo $e((string) ($php['memoryUsage'] ?? '—')) ?> / <?php echo $e((string) ($php['memoryPeak'] ?? '—')) ?></dd></div>
            <div><dt>OPcache</dt><dd><?php echo !empty($php['opcache']) ? $e($t('admin.common.yes')) : $e($t('admin.common.no')) ?></dd></div>
        </dl>
        <?php if (($php['extensions'] ?? []) !== []): ?>
            <p class="muted" style="margin-top:.65rem;font-size:.85rem">
                <?php echo $e($t('admin.health.php_extensions')) ?>:
                <?php echo $e(implode(', ', $php['extensions'])) ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="card dash-card">
        <h2><?php echo $e($t('admin.health.runtime')) ?></h2>
        <?php $dBadge = $statusBadge($db === 'ok' ? 'ok' : 'degraded'); ?>
        <p><span class="badge <?php echo $e($dBadge['class']) ?>"><?php echo $e($t('admin.health.database')) ?>: <?php echo $e($dBadge['label']) ?></span></p>
        <dl class="health-dl">
            <div><dt><?php echo $e($t('admin.health.app_version')) ?></dt><dd><?php echo $e((string) ($app['version'] ?? '')) ?></dd></div>
            <div><dt><?php echo $e($t('admin.health.app_env')) ?></dt><dd><code><?php echo $e((string) ($app['env'] ?? '')) ?></code></dd></div>
            <div><dt><?php echo $e($t('admin.health.app_debug')) ?></dt><dd><?php echo !empty($app['debug']) ? $e($t('admin.common.yes')) : $e($t('admin.common.no')) ?></dd></div>
            <div><dt><?php echo $e($t('admin.health.app_timezone')) ?></dt><dd><?php echo $e((string) ($app['timezone'] ?? '')) ?></dd></div>
        </dl>
    </section>
</div>

<style>
    .health-dl { margin: .65rem 0 0; display: grid; gap: .35rem; }
    .health-dl > div { display: flex; justify-content: space-between; gap: 1rem; font-size: .92rem; }
    .health-dl dt { color: #78716c; margin: 0; }
    .health-dl dd { margin: 0; text-align: right; }
</style>
