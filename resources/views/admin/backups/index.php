<?php

use Nexis\Infrastructure\Backup\BackupEntry;
use Nexis\Infrastructure\Backup\BackupSchedule;
use Nexis\Infrastructure\Health\SystemHealthReport;

/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<BackupEntry> $backups */
$backups = $backups ?? [];
/** @var BackupSchedule $schedule */
$size = static fn (int $bytes): string => SystemHealthReport::formatBytes($bytes);
?>
<h1><?php echo $e($t('admin.backups.title')) ?></h1>
<?php if ($notice !== ''): ?><p class="flash flash--success" role="status"><?php echo $e($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>
<p class="muted"><?php echo $e($t('admin.backups.intro')) ?></p>

<?php if ($mysqldumpMissing): ?>
    <p class="flash flash--error" role="alert"><?php echo $e($t('admin.backups.mysqldump_missing')) ?></p>
<?php endif; ?>

<div class="card">
    <h2><?php echo $e($t('admin.backups.create_heading')) ?></h2>
    <p class="muted"><?php echo $e($t('admin.backups.create_hint')) ?></p>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/backups">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label class="check-label">
            <input type="checkbox" name="include_media" value="1" checked>
            <?php echo $e($t('admin.backups.include_media')) ?>
        </label>
        <p style="margin:0">
            <button type="submit"><?php echo $e($t('admin.backups.create')) ?></button>
        </p>
    </form>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.backups.schedule_heading')) ?></h2>
    <p class="muted"><?php echo $e($t('admin.backups.schedule_hint')) ?></p>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/backups/schedule">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label class="check-label">
            <input type="checkbox" name="enabled" value="1"<?php echo $schedule->enabled ? ' checked' : '' ?>>
            <?php echo $e($t('admin.backups.schedule_enabled')) ?>
        </label>
        <div class="row">
            <div>
                <label for="interval"><?php echo $e($t('admin.backups.schedule_interval')) ?></label>
                <select id="interval" name="interval">
                    <?php foreach ($intervals as $option): ?>
                        <option value="<?php echo $e($option) ?>"<?php echo $schedule->interval === $option ? ' selected' : '' ?>>
                            <?php echo $e($t('admin.backups.interval_' . $option)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="retain"><?php echo $e($t('admin.backups.schedule_retain')) ?></label>
                <input id="retain" name="retain" type="number" min="<?php echo $e((string) $retainMin) ?>"
                       max="<?php echo $e((string) $retainMax) ?>" value="<?php echo $e((string) $schedule->retain) ?>">
            </div>
        </div>
        <label class="check-label" style="margin-top:1rem">
            <input type="checkbox" name="schedule_include_media" value="1"<?php echo $schedule->includeMedia ? ' checked' : '' ?>>
            <?php echo $e($t('admin.backups.include_media')) ?>
        </label>
        <p style="margin:0">
            <button type="submit"><?php echo $e($t('admin.common.save')) ?></button>
        </p>
        <p class="muted" style="margin:.85rem 0 0;font-size:.85rem">
            <?php echo $e($t('admin.backups.schedule_last_run')) ?>:
            <?php echo $schedule->lastRunAt !== null
                ? $e($schedule->lastRunAt->format('Y-m-d H:i') . ' UTC')
                : $e($t('admin.common.empty_dash')) ?>
        </p>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem"><?php echo $e($t('admin.backups.schedule_cron')) ?></p>
        <p class="muted" style="margin:.35rem 0 0;font-size:.85rem"><code>php bin/backup.php --scheduled</code></p>
    </form>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.backups.list_heading')) ?></h2>
    <?php if ($backups === []): ?>
        <p class="muted"><?php echo $e($t('admin.backups.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?php echo $e($t('admin.backups.stamp')) ?></th>
                <th><?php echo $e($t('admin.backups.created_at')) ?></th>
                <th><?php echo $e($t('admin.backups.parts')) ?></th>
                <th><?php echo $e($t('admin.backups.size')) ?></th>
                <th><?php echo $e($t('admin.common.actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($backups as $entry): ?>
                <tr>
                    <td><code><?php echo $e($entry->stamp) ?></code></td>
                    <td class="muted"><?php echo $e($entry->createdAt->format('Y-m-d H:i') . ' UTC') ?></td>
                    <td>
                        <?php if ($entry->restorable()): ?>
                            <span class="badge badge-admin">database.sql</span>
                        <?php else: ?>
                            <span class="badge badge-editor"><?php echo $e($t('admin.backups.no_database')) ?></span>
                        <?php endif; ?>
                        <?php if ($entry->hasMedia): ?>
                            <span class="badge"><?php echo $e($t('admin.backups.media_files', ['count' => (string) $entry->mediaFiles])) ?></span>
                        <?php endif; ?>
                        <?php if ($entry->database() !== null): ?>
                            <br><span class="muted" style="font-size:.85rem"><?php echo $e($entry->database()) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="muted">
                        <?php echo $e($size($entry->bytes)) ?>
                        <?php if ($entry->restorable()): ?>
                            <br><span style="font-size:.85rem">SQL <?php echo $e($size($entry->databaseBytes)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="row-actions">
                            <form method="post" action="<?php echo $e($basePath) ?>/admin/backups/download">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <input type="hidden" name="stamp" value="<?php echo $e($entry->stamp) ?>">
                                <button type="submit" class="btn-small"><?php echo $e($t('admin.backups.download_zip')) ?></button>
                            </form>
                            <?php if ($entry->restorable()): ?>
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/backups/download-sql">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="stamp" value="<?php echo $e($entry->stamp) ?>">
                                    <button type="submit" class="btn-small btn-muted"><?php echo $e($t('admin.backups.download_sql')) ?></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?php echo $e($basePath) ?>/admin/backups/delete"
                                  data-confirm-title="<?php echo $e($t('admin.backups.delete_title')) ?>"
                                  data-confirm="<?php echo $e($t('admin.backups.delete_confirm', ['stamp' => $entry->stamp])) ?>"
                                  data-confirm-detail="<?php echo $e($t('admin.backups.delete_detail')) ?>"
                                  data-confirm-ok="<?php echo $e($t('admin.common.delete')) ?>">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <input type="hidden" name="stamp" value="<?php echo $e($entry->stamp) ?>">
                                <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.common.delete')) ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted" style="margin:.85rem 0 0;font-size:.85rem">
            <?php echo $e($t('admin.backups.total', ['count' => (string) count($backups), 'size' => $size((int) $totalBytes)])) ?>
        </p>
    <?php endif; ?>
    <p class="muted" style="margin:.85rem 0 0;font-size:.85rem">
        <?php echo $e($t('admin.backups.storage_path')) ?>: <code><?php echo $e($backupsPath) ?></code>
    </p>
    <p class="muted" style="margin:.35rem 0 0;font-size:.85rem"><?php echo $e($t('admin.backups.restore_hint')) ?></p>
</div>
