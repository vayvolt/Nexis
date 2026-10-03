<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var \Nexis\Plugins\Forms\FormDefinition $form */
/** @var list<string> $fieldTypes */
/** @var list<string> $conditionOps */
$fieldTypes = $fieldTypes ?? \Nexis\Plugins\Forms\FormField::TYPES;
$conditionOps = $conditionOps ?? \Nexis\Plugins\Forms\FormField::OPS;
?>
<p class="muted"><a href="<?php echo $e($basePath) ?>/admin/forms/builder"><?php echo $e($t('admin.forms.tab_builder')) ?></a></p>
<h1><?php echo $e($t('admin.forms.builder.edit_title', ['name' => $form->name])) ?></h1>
<p class="muted" style="margin:.35rem 0 1rem">
    <a class="btn btn-small btn-muted" href="<?php echo $e($basePath) ?>/admin/forms"><?php echo $e($t('admin.forms.tab_inbox')) ?></a>
    <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/forms/builder"><?php echo $e($t('admin.forms.tab_builder')) ?></a>
</p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.forms.updated')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<form method="post" action="<?php echo $e($basePath) ?>/admin/forms/builder/<?php echo $e(rawurlencode($form->id)) ?>">
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">

    <div class="card">
        <h2><?php echo $e($t('admin.forms.builder.meta')) ?></h2>
        <div class="row">
            <div>
                <label for="name"><?php echo $e($t('admin.forms.builder.name')) ?></label>
                <input id="name" name="name" value="<?php echo $e($form->name) ?>" required>
            </div>
            <div>
                <label for="slug"><?php echo $e($t('admin.forms.builder.slug')) ?></label>
                <input id="slug" name="slug" value="<?php echo $e($form->slug) ?>" <?php echo $form->slug === 'contact' ? 'readonly' : '' ?>>
            </div>
            <div>
                <label for="submit_label"><?php echo $e($t('admin.forms.builder.submit_label')) ?></label>
                <input id="submit_label" name="submit_label" value="<?php echo $e((string) ($form->submitLabel ?? '')) ?>">
            </div>
            <div style="flex:1 1 100%">
                <label for="success_message"><?php echo $e($t('admin.forms.builder.success_message')) ?></label>
                <input id="success_message" name="success_message" value="<?php echo $e((string) ($form->successMessage ?? '')) ?>">
            </div>
        </div>
        <p class="muted" style="margin:.5rem 0 0"><?php echo $e($t('admin.forms.builder.form_id_hint', ['id' => $form->id !== '' ? $form->id : $form->slug])) ?></p>
    </div>

    <div class="card">
        <h2><?php echo $e($t('admin.forms.builder.fields')) ?></h2>
        <?php if ($form->fields === []): ?>
            <p class="muted"><?php echo $e($t('admin.forms.builder.no_fields')) ?></p>
        <?php endif; ?>
        <?php
        $prevKeys = [];
        foreach ($form->fields as $i => $field):
            $optionsText = '';
            foreach ($field->options as $opt) {
                $optionsText .= $opt['value'] === $opt['label']
                    ? $opt['value'] . "\n"
                    : $opt['value'] . '|' . $opt['label'] . "\n";
            }
            $cond = $field->visibleWhen;
            ?>
            <fieldset style="margin:0 0 1rem;padding:0.85rem;border:1px solid #e7e5e4;border-radius:8px">
                <legend><?php echo $e($t('admin.forms.builder.field_n', ['n' => (string) ($i + 1)])) ?></legend>
                <div class="row">
                    <div>
                        <label><?php echo $e($t('admin.forms.builder.field_key')) ?>
                            <input name="fields[<?php echo $e((string) $i) ?>][key]" value="<?php echo $e($field->key) ?>" required>
                        </label>
                    </div>
                    <div>
                        <label><?php echo $e($t('admin.forms.builder.field_label')) ?>
                            <input name="fields[<?php echo $e((string) $i) ?>][label]" value="<?php echo $e($field->label) ?>" required>
                        </label>
                    </div>
                    <div>
                        <label><?php echo $e($t('admin.forms.builder.field_type')) ?>
                            <select name="fields[<?php echo $e((string) $i) ?>][type]">
                                <?php foreach ($fieldTypes as $type): ?>
                                    <option value="<?php echo $e($type) ?>" <?php echo $field->type === $type ? 'selected' : '' ?>><?php echo $e($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div>
                        <label><?php echo $e($t('admin.forms.builder.field_placeholder')) ?>
                            <input name="fields[<?php echo $e((string) $i) ?>][placeholder]" value="<?php echo $e($field->placeholder) ?>">
                        </label>
                    </div>
                    <div style="flex:0">
                        <label class="check-label" style="margin-top:1.6rem">
                            <input type="checkbox" name="fields[<?php echo $e((string) $i) ?>][required]" value="1" <?php echo $field->required ? 'checked' : '' ?>>
                            <?php echo $e($t('admin.forms.builder.field_required')) ?>
                        </label>
                    </div>
                </div>
                <div style="margin-top:.65rem">
                    <label><?php echo $e($t('admin.forms.builder.field_options')) ?>
                        <textarea name="fields[<?php echo $e((string) $i) ?>][options]" rows="3" placeholder="ja|Ja&#10;nein|Nein"><?php echo $e(rtrim($optionsText)) ?></textarea>
                    </label>
                    <span class="muted" style="font-size:.85rem"><?php echo $e($t('admin.forms.builder.field_options_hint')) ?></span>
                </div>
                <div class="row" style="margin-top:.65rem">
                    <div>
                        <label><?php echo $e($t('admin.forms.builder.cond_field')) ?>
                            <select name="fields[<?php echo $e((string) $i) ?>][cond_field]">
                                <option value=""><?php echo $e($t('admin.forms.builder.cond_none')) ?></option>
                                <?php foreach ($prevKeys as $key): ?>
                                    <option value="<?php echo $e($key) ?>" <?php echo ($cond['field'] ?? '') === $key ? 'selected' : '' ?>><?php echo $e($key) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div>
                        <label><?php echo $e($t('admin.forms.builder.cond_op')) ?>
                            <select name="fields[<?php echo $e((string) $i) ?>][cond_op]">
                                <?php foreach ($conditionOps as $op): ?>
                                    <option value="<?php echo $e($op) ?>" <?php echo ($cond['op'] ?? 'eq') === $op ? 'selected' : '' ?>><?php echo $e($op) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div>
                        <label><?php echo $e($t('admin.forms.builder.cond_value')) ?>
                            <input name="fields[<?php echo $e((string) $i) ?>][cond_value]" value="<?php echo $e((string) ($cond['value'] ?? '')) ?>">
                        </label>
                    </div>
                </div>
                <p style="margin:.75rem 0 0;display:flex;gap:.4rem;flex-wrap:wrap">
                    <button type="submit" name="action" value="up:<?php echo $e((string) $i) ?>" class="btn-small" <?php echo $i === 0 ? 'disabled' : '' ?>><?php echo $e($t('admin.forms.builder.move_up')) ?></button>
                    <button type="submit" name="action" value="down:<?php echo $e((string) $i) ?>" class="btn-small" <?php echo $i >= count($form->fields) - 1 ? 'disabled' : '' ?>><?php echo $e($t('admin.forms.builder.move_down')) ?></button>
                    <button type="submit" name="action" value="remove:<?php echo $e((string) $i) ?>" class="btn-small" style="background:#b91c1c"><?php echo $e($t('admin.common.delete')) ?></button>
                </p>
            </fieldset>
        <?php
            $prevKeys[] = $field->key;
        endforeach;
        ?>

        <div class="row" style="align-items:flex-end;margin-top:1rem">
            <div>
                <label for="new_field_type"><?php echo $e($t('admin.forms.builder.add_field')) ?></label>
                <select id="new_field_type" name="new_field_type">
                    <?php foreach ($fieldTypes as $type): ?>
                        <option value="<?php echo $e($type) ?>"><?php echo $e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0">
                <button type="submit" name="action" value="add" class="btn-small"><?php echo $e($t('admin.forms.builder.add')) ?></button>
            </div>
        </div>
    </div>

    <p>
        <button type="submit" name="action" value="save"><?php echo $e($t('admin.common.save')) ?></button>
    </p>
</form>
