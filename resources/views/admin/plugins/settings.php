<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<p class="muted"><a href="<?php echo $e($basePath) ?>/admin/plugins"><?php echo $e($t('admin.plugins.title')) ?></a></p>
<h1><?php echo $e($t('admin.plugins.settings.title', ['plugin' => (string) $pluginName])) ?></h1>
<p class="muted"><code><?php echo $e((string) $pluginId) ?></code></p>

<?php if (($notice ?? '') !== ''): ?>
    <p class="flash flash--success" role="status"><?php echo $e($notice) ?></p>
<?php endif; ?>
<?php if (($error ?? '') !== ''): ?>
    <p class="flash flash--error" role="alert"><?php echo $e($error) ?></p>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/settings">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="plugin" value="<?php echo $e((string) $pluginId) ?>">
        <?php foreach (($fields ?? []) as $field): ?>
            <?php
            $name = (string) ($field['name'] ?? '');
            $type = (string) ($field['type'] ?? 'string');
            $label = (string) ($field['label'] ?? $name);
            $value = $field['value'] ?? '';
            $inputId = 'field_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
            ?>
            <p>
                <label for="<?php echo $e($inputId) ?>"><?php echo $e($label) ?></label>
                <?php if ($type === 'bool'): ?>
                    <label class="check-label">
                        <input id="<?php echo $e($inputId) ?>" type="checkbox" name="field_<?php echo $e($name) ?>" value="1"<?php echo !empty($value) ? ' checked' : '' ?>>
                        <?php echo $e($t('admin.common.enabled')) ?>
                    </label>
                <?php elseif ($type === 'secret'): ?>
                    <input id="<?php echo $e($inputId) ?>" type="password" name="field_<?php echo $e($name) ?>" value="" autocomplete="new-password" placeholder="<?php echo $e($t('admin.plugins.settings.secret_placeholder')) ?>">
                <?php elseif ($type === 'int'): ?>
                    <input id="<?php echo $e($inputId) ?>" type="number" name="field_<?php echo $e($name) ?>" value="<?php echo $e((string) (int) $value) ?>">
                <?php elseif ($type === 'email'): ?>
                    <input id="<?php echo $e($inputId) ?>" type="email" name="field_<?php echo $e($name) ?>" value="<?php echo $e((string) $value) ?>">
                <?php elseif ($type === 'url'): ?>
                    <input id="<?php echo $e($inputId) ?>" type="url" name="field_<?php echo $e($name) ?>" value="<?php echo $e((string) $value) ?>">
                <?php else: ?>
                    <input id="<?php echo $e($inputId) ?>" type="text" name="field_<?php echo $e($name) ?>" value="<?php echo $e((string) $value) ?>">
                <?php endif; ?>
            </p>
        <?php endforeach; ?>
        <p><button type="submit"><?php echo $e($t('admin.common.save')) ?></button></p>
    </form>
</div>
