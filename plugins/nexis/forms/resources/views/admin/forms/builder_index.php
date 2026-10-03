<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<\Nexis\Plugins\Forms\FormDefinition> $forms */
$forms = $forms ?? [];
?>
<h1><?php echo $e($t('admin.forms.title')) ?></h1>
<p class="muted" style="margin:.35rem 0 1rem">
    <a class="btn btn-small btn-muted" href="<?php echo $e($basePath) ?>/admin/forms"><?php echo $e($t('admin.forms.tab_inbox')) ?></a>
    <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/forms/builder"><?php echo $e($t('admin.forms.tab_builder')) ?></a>
</p>
<p class="muted"><?php echo $e($t('admin.forms.builder.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.forms.updated')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<div class="card">
    <h2><?php echo $e($t('admin.forms.builder.new')) ?></h2>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/builder" class="row" style="align-items:flex-end;margin:0">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <div>
            <label for="new-name"><?php echo $e($t('admin.forms.builder.name')) ?></label>
            <input id="new-name" name="name" required placeholder="<?php echo $e($t('admin.forms.builder.name_placeholder')) ?>">
        </div>
        <div>
            <label for="new-slug"><?php echo $e($t('admin.forms.builder.slug')) ?></label>
            <input id="new-slug" name="slug" placeholder="newsletter">
        </div>
        <div style="flex:0">
            <button type="submit"><?php echo $e($t('admin.common.create')) ?></button>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.forms.builder.name')) ?></th>
                <th><?php echo $e($t('admin.forms.builder.slug')) ?></th>
                <th><?php echo $e($t('admin.forms.builder.fields')) ?></th>
                <th style="width:1%;white-space:nowrap"><?php echo $e($t('admin.common.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($forms === []): ?>
                <tr><td colspan="4" class="muted"><?php echo $e($t('admin.forms.builder.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($forms as $form): ?>
                <tr>
                    <td><strong><a href="<?php echo $e($basePath) ?>/admin/forms/builder/<?php echo $e(rawurlencode($form->id)) ?>"><?php echo $e($form->name) ?></a></strong></td>
                    <td><code><?php echo $e($form->slug) ?></code></td>
                    <td class="muted"><?php echo $e($t('admin.forms.builder.field_count', ['count' => (string) count($form->fields)])) ?></td>
                    <td class="row-actions">
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/forms/builder/<?php echo $e(rawurlencode($form->id)) ?>"><?php echo $e($t('admin.common.edit')) ?></a>
                        <?php if ($form->slug !== \Nexis\Plugins\Forms\FormDefinition::CONTACT_SLUG): ?>
                            <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/builder/<?php echo $e(rawurlencode($form->id)) ?>/delete" style="display:inline" onsubmit="return confirm(<?php echo json_encode($t('admin.forms.builder.delete_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.common.delete')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.forms.builder.usage')) ?></h2>
    <p class="muted" style="margin:0"><?php echo $e($t('admin.forms.builder.usage_hint')) ?></p>
</div>
