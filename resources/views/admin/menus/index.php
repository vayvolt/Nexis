<?php
/** @var array<string, string> $handles */
/** @var array<string, array{items: list<array{item: mixed, parent: string}>, pages: list<\Nexis\Content\Page>}> $byLocale */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$locales = $site->enabledLocales();
$byLocale = $byLocale ?? [];
$rowCount = max(1, (int) ($rowCount ?? 1));
$defaultLocale = $site->defaultLocale;
$defaultItems = $byLocale[$defaultLocale]['items'] ?? [];
$isPrimary = $handle === 'primary';

$defaultLabels = [];
for ($r = 0; $r < $rowCount; $r++) {
    $row = $defaultItems[$r] ?? ['item' => null, 'parent' => ''];
    $item = $row['item'] ?? null;
    $defaultLabels[$r] = trim((string) ($item->label ?? ''));
}
?>
<h1><?php echo $e($t('admin.menus.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.menus.intro_tabs')) ?></p>

<?php if (!empty($saved)): ?>
    <p class="flash flash--success" role="status"><?php echo $e($t('admin.menus.saved')) ?></p>
<?php endif; ?>
<?php if (($error ?? '') !== ''): ?>
    <p class="flash flash--error" role="alert"><?php echo $e($error) ?></p>
<?php endif; ?>

<div class="card">
    <p class="dash-links" style="margin:0" role="navigation" aria-label="<?php echo $e($t('admin.menus.choose')) ?>">
        <?php foreach ($handles as $h => $label): ?>
            <a
                class="btn btn-small<?php echo $handle === $h ? '' : ' btn-muted' ?>"
                href="<?php echo $e($basePath) ?>/admin/menus?handle=<?php echo $e($h) ?>"
                <?php echo $handle === $h ? 'aria-current="page"' : '' ?>
            ><?php echo $e($t('admin.menus.' . $h, [], $label)) ?></a>
        <?php endforeach; ?>
    </p>
</div>

<style>
.menu-sort-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: 1px solid var(--line);
  border-radius: .35rem;
  background: #fff;
  color: var(--muted);
  cursor: grab;
  user-select: none;
  font-size: .85rem;
  line-height: 1;
}
.menu-sort-handle:active { cursor: grabbing; }
.menu-sort-handle:focus {
  outline: 2px solid var(--accent);
  outline-offset: 1px;
  color: var(--text);
}
.menu-row-actions {
  display: inline-flex;
  flex-wrap: nowrap;
  gap: .25rem;
  align-items: center;
  white-space: nowrap;
}
.menu-row-actions .btn,
.menu-row-actions .btn-small,
.menu-row-actions button {
  margin: 0;
  flex: 0 0 auto;
  width: auto;
  min-width: 1.85rem;
  padding: .25rem .4rem;
}
tr.is-dragging { opacity: .55; }
tr.is-drag-over { outline: 2px dashed var(--accent); outline-offset: -2px; }
.menu-editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  align-items: center;
  margin: 0 0 .75rem;
}
</style>

<form
    method="post"
    action="<?php echo $e($basePath) ?>/admin/menus"
    data-menu-editor
    data-menu-primary="<?php echo $isPrimary ? '1' : '0' ?>"
    data-menu-default-locale="<?php echo $e($defaultLocale) ?>"
    data-i18n-parent-none="<?php echo $e($t('admin.menus.parent_none')) ?>"
    data-i18n-parent-fallback="<?php echo $e($t('admin.menus.parent_row')) ?>"
    data-i18n-drag="<?php echo $e($t('admin.menus.drag_hint')) ?>"
    data-i18n-move-up="<?php echo $e($t('admin.menus.move_up')) ?>"
    data-i18n-move-down="<?php echo $e($t('admin.menus.move_down')) ?>"
    data-i18n-remove="<?php echo $e($t('admin.menus.remove_row')) ?>"
    data-i18n-label-ph="<?php echo $e($t($handle === 'footer' ? 'admin.menus.placeholder_footer' : 'admin.menus.placeholder_item')) ?>"
    data-i18n-external="<?php echo $e($t('admin.menus.external_url')) ?>"
>
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
    <input type="hidden" name="handle" value="<?php echo $e($handle) ?>">

    <div class="card locale-tabs" data-locale-tabs>
        <h2><?php echo $e($t('admin.menus.texts')) ?></h2>
        <p class="muted form-hint" style="margin:0 0 .75rem"><?php echo $e($t('admin.menus.drag_hint')) ?></p>
        <div class="menu-editor-toolbar">
            <button type="button" class="btn btn-small" data-menu-add><?php echo $e($t('admin.menus.add_row')) ?></button>
            <span class="muted" style="font-size:.85rem"><?php echo $e($t('admin.menus.keyboard_hint')) ?></span>
        </div>
        <?php if (count($locales) > 1): ?>
            <div class="locale-tabs__list" role="tablist" aria-label="<?php echo $e($t('admin.settings.locales')) ?>">
                <?php foreach ($locales as $i => $loc): ?>
                    <button
                        type="button"
                        class="locale-tabs__tab"
                        role="tab"
                        id="menu-tab-<?php echo $e($loc->locale) ?>"
                        data-locale-tab="<?php echo $e($loc->locale) ?>"
                        aria-controls="menu-panel-<?php echo $e($loc->locale) ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false' ?>"
                        tabindex="<?php echo $i === 0 ? '0' : '-1' ?>"
                    ><?php echo $e($t('admin.locale.' . $loc->locale, [], $loc->label)) ?> <code><?php echo $e($loc->locale) ?></code></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($locales as $i => $loc): ?>
            <?php
            $code = $loc->locale;
            $localeItems = $byLocale[$code]['items'] ?? [];
            $localePages = $byLocale[$code]['pages'] ?? [];
            $isDefault = $code === $defaultLocale;
            ?>
            <div
                class="locale-tabs__panel"
                role="tabpanel"
                id="menu-panel-<?php echo $e($code) ?>"
                data-locale-panel="<?php echo $e($code) ?>"
                aria-labelledby="menu-tab-<?php echo $e($code) ?>"
                <?php echo $i === 0 ? '' : 'hidden' ?>
            >
                <div class="table-wrap" style="margin:0">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:2.5rem" aria-label="<?php echo $e($t('admin.menus.drag_hint')) ?>"></th>
                                <th style="width:3rem">#</th>
                                <th><?php echo $e($t('admin.menus.col_label')) ?></th>
                                <?php if ($isPrimary): ?>
                                    <th style="min-width:10rem"><?php echo $e($t('admin.menus.col_parent')) ?></th>
                                <?php endif; ?>
                                <th><?php echo $e($t('admin.menus.col_page')) ?></th>
                                <th><?php echo $e($t('admin.menus.col_url')) ?></th>
                                <th style="width:1%;white-space:nowrap;min-width:6.5rem"><?php echo $e($t('admin.common.actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody data-menu-sortable data-menu-locale="<?php echo $e($code) ?>">
                            <?php for ($r = 0; $r < $rowCount; $r++): ?>
                                <?php
                                $row = $localeItems[$r] ?? ($defaultItems[$r] ?? ['item' => null, 'parent' => '']);
                                $item = $row['item'] ?? null;
                                $parent = (string) ($row['parent'] ?? '');
                                if ($item === null && isset($defaultItems[$r]['parent'])) {
                                    $parent = (string) $defaultItems[$r]['parent'];
                                }
                                $parentLabel = '—';
                                if ($parent !== '' && isset($defaultLabels[(int) $parent]) && $defaultLabels[(int) $parent] !== '') {
                                    $parentLabel = $defaultLabels[(int) $parent];
                                } elseif ($parent !== '') {
                                    $parentLabel = $t('admin.menus.parent_row', ['n' => $parent]);
                                }
                                ?>
                                <tr data-menu-index="<?php echo (int) $r ?>">
                                    <td>
                                        <span
                                            class="menu-sort-handle"
                                            data-menu-drag
                                            draggable="true"
                                            role="button"
                                            tabindex="0"
                                            aria-label="<?php echo $e($t('admin.menus.drag_hint')) ?>"
                                            title="<?php echo $e($t('admin.menus.drag_hint')) ?>"
                                        >⋮⋮</span>
                                    </td>
                                    <td class="muted" data-menu-index-label><?php echo (int) $r ?></td>
                                    <td>
                                        <input
                                            name="label[<?php echo $e($code) ?>][<?php echo (int) $r ?>]"
                                            value="<?php echo $e($item->label ?? '') ?>"
                                            placeholder="<?php echo $e($t($handle === 'footer' ? 'admin.menus.placeholder_footer' : 'admin.menus.placeholder_item')) ?>"
                                            data-menu-label
                                        >
                                    </td>
                                    <?php if ($isPrimary): ?>
                                        <td>
                                            <?php if ($isDefault): ?>
                                                <select name="parent[<?php echo (int) $r ?>]" data-menu-parent>
                                                    <option value=""><?php echo $e($t('admin.menus.parent_none')) ?></option>
                                                    <?php for ($p = 0; $p < $rowCount; $p++): ?>
                                                        <?php
                                                        if ($p === $r || $defaultLabels[$p] === '') {
                                                            continue;
                                                        }
                                                        $pParent = (string) (($defaultItems[$p]['parent'] ?? '') ?: '');
                                                        if ($pParent !== '') {
                                                            continue;
                                                        }
                                                        ?>
                                                        <option value="<?php echo (int) $p ?>" <?php echo $parent === (string) $p ? 'selected' : '' ?>>
                                                            <?php echo $e($defaultLabels[$p]) ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            <?php else: ?>
                                                <span class="muted" data-menu-parent-display><?php echo $e($parentLabel) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php elseif ($isDefault): ?>
                                        <input type="hidden" name="parent[<?php echo (int) $r ?>]" value="" data-menu-parent>
                                    <?php endif; ?>
                                    <td>
                                        <select name="page_id[<?php echo $e($code) ?>][<?php echo (int) $r ?>]" data-menu-page>
                                            <option value=""><?php echo $e($t('admin.menus.external_url')) ?></option>
                                            <?php foreach ($localePages as $p): ?>
                                                <option value="<?php echo $e($p->id->value) ?>" <?php echo ($item !== null && $item->pageId !== null && $item->pageId->value === $p->id->value) ? 'selected' : '' ?>>
                                                    <?php echo $e($p->title) ?> (<?php echo $e($p->path) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input name="url[<?php echo $e($code) ?>][<?php echo (int) $r ?>]" value="<?php echo $e($item->url ?? '') ?>" placeholder="/de oder https://…" data-menu-url>
                                    </td>
                                    <td style="white-space:nowrap">
                                        <div class="menu-row-actions">
                                            <button type="button" class="btn btn-small btn-muted" data-menu-move-up title="<?php echo $e($t('admin.menus.move_up')) ?>" aria-label="<?php echo $e($t('admin.menus.move_up')) ?>">↑</button>
                                            <button type="button" class="btn btn-small btn-muted" data-menu-move-down title="<?php echo $e($t('admin.menus.move_down')) ?>" aria-label="<?php echo $e($t('admin.menus.move_down')) ?>">↓</button>
                                            <button type="button" class="btn-danger btn-small" data-menu-remove title="<?php echo $e($t('admin.menus.remove_row')) ?>" aria-label="<?php echo $e($t('admin.menus.remove_row')) ?>">×</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <p class="form-hint" style="margin:0 0 .75rem">
            <?php echo $e($t('admin.menus.empty_rows')) ?>
            <?php if ($isPrimary): ?>
                <?php echo $e($t('admin.menus.parent_hint')) ?>
            <?php endif; ?>
        </p>
        <p style="margin:0"><button type="submit"><?php echo $e($t('admin.common.save')) ?></button></p>
    </div>
</form>
<script src="<?php echo $e($basePath) ?>/assets/admin/menus-sortable.js" defer></script>
