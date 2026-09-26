<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($t('admin.export.title')) ?></h1>
<?php if ($notice !== ''): ?><p class="flash flash--success" role="status"><?php echo $e($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>
<p class="muted"><?php echo $e($t('admin.export.intro')) ?></p>

<div class="card">
    <h2><?php echo $e($t('admin.export.export')) ?></h2>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/export/download">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <button type="submit"><?php echo $e($t('admin.export.download')) ?></button>
    </form>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.export.import')) ?></h2>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/export/import" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label for="archive"><?php echo $e($t('admin.export.archive')) ?></label>
        <input id="archive" type="file" name="archive" accept=".zip,application/zip" required>
        <p><button type="submit"><?php echo $e($t('admin.export.import_btn')) ?></button></p>
    </form>
</div>
