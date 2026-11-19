<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$flashClass = match ($noticeTone ?? 'success') {
    'info' => 'flash--info',
    'error' => 'flash--error',
    default => 'flash--success',
};
?>
<h1><?php echo $e($t('admin.export.title')) ?></h1>
<?php if ($notice !== ''): ?><p class="flash <?php echo $e($flashClass) ?>" role="status"><?php echo $e($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>
<p class="muted"><?php echo $e($t('admin.export.intro')) ?></p>
<p class="muted"><?php echo $e($t('admin.export.scope_hint')) ?></p>

<div class="card">
    <h2><?php echo $e($t('admin.export.export')) ?></h2>
    <p class="muted"><?php echo $e($t('admin.export.export_hint')) ?></p>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/export/download">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <button type="submit"><?php echo $e($t('admin.export.download')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.export.import')) ?></h2>
    <p class="muted"><?php echo $e($t('admin.export.import_hint')) ?></p>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/export/import" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label for="archive"><?php echo $e($t('admin.export.archive')) ?></label>
        <input id="archive" type="file" name="archive" accept=".zip,application/zip" required>
        <p>
            <label>
                <input type="checkbox" name="dry_run" value="1">
                <?php echo $e($t('admin.export.dry_run')) ?>
            </label>
        </p>
        <p><button type="submit"><?php echo $e($t('admin.export.import_btn')) ?></button></p>
    </form>
</div>
