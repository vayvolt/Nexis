<?php
/** @var array<string, string> $handles */
/** @var array<string, array{items: list<array{item: mixed, parent: string}>, pages: list<\Nexis\Content\Page>}> $byLocale */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$locales = $site->enabledLocales();
$byLocale = $byLocale ?? [];
$rowCount = (int) ($rowCount ?? 8);
$defaultLocale = $site->defaultLocale;
$defaultItems = $byLocale[$defaultLocale]['items'] ?? [];
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
    <p class="muted" style="margin:0 0 .75rem"><?php echo $e($t('admin.menus.choose')) ?></p>
    <p class="dash-links" style="margin:0">
        <?php foreach ($handles as $h => $label): ?>
            <a
                class="btn btn-small<?php echo $handle === $h ? '' : ' btn-muted' ?>"
                href="<?php echo $e($basePath) ?>/admin/menus?handle=<?php echo $e($h) ?>"
                <?php echo $handle === $h ? 'aria-current="page"' : '' ?>
            ><?php echo $e($t('admin.menus.' . $h, [], $label)) ?></a>
        <?php endforeach; ?>
    </p>
    <p class="muted" style="margin:.85rem 0 0">
        Handle <code><?php echo $e($handle) ?></code>
        <?php if ($handle === 'footer'): ?>
            · <?php echo $e($t('admin.menus.footer_hint')) ?>
        <?php else: ?>
            · <?php echo $e($t('admin.menus.primary_hint')) ?>
        <?php endif; ?>
    </p>
</div>

<style>
.menu-sort-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border: 1px solid var(--admin-border, #d5dbe5);
  border-radius: .35rem;
  background: var(--admin-surface, #fff);
  color: var(--admin-muted, #5b6577);
  cursor: grab;
  user-select: none;
  font-size: .85rem;
  line-height: 1;
}
.menu-sort-handle:active { cursor: grabbing; }
tr.is-dragging { opacity: .55; }
tr.is-drag-over { outline: 2px dashed var(--admin-accent, #4f8fd8); outline-offset: -2px; }
</style>

<form method="post" action="<?php echo $e($basePath) ?>/admin/menus">
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
    <input type="hidden" name="handle" value="<?php echo $e($handle) ?>">

    <div class="card locale-tabs" data-locale-tabs>
        <h2><?php echo $e($t('admin.menus.texts')) ?></h2>
        <p class="muted" style="margin:0 0 .75rem"><?php echo $e($t('admin.menus.drag_hint')) ?></p>
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
            ?>
            <div
                class="locale-tabs__panel"
                role="tabpanel"
                id="menu-panel-<?php echo $e($code) ?>"
                data-locale-panel="<?php echo $e($code) ?>"
                aria-labelledby="menu-tab-<?php echo $e($code) ?>"
                <?php echo $i === 0 ? '' : 'hidden' ?>
                style="padding:0;overflow:auto"
            >
                <table>
                    <thead>
                        <tr>
                            <th style="width:2.5rem" aria-label="<?php echo $e($t('admin.menus.drag_hint')) ?>"></th>
                            <th style="width:3rem">#</th>
                            <th><?php echo $e($t('admin.menus.col_label')) ?></th>
                            <?php if ($handle === 'primary'): ?>
                                <th style="width:6rem"><?php echo $e($t('admin.menus.col_parent')) ?></th>
                            <?php endif; ?>
                            <th><?php echo $e($t('admin.menus.col_page')) ?></th>
                            <th><?php echo $e($t('admin.menus.col_url')) ?></th>
                        </tr>
                    </thead>
                    <tbody data-menu-sortable>
                        <?php for ($r = 0; $r < $rowCount; $r++): ?>
                            <?php
                            $row = $localeItems[$r] ?? ($defaultItems[$r] ?? ['item' => null, 'parent' => '']);
                            $item = $row['item'] ?? null;
                            $parent = (string) ($row['parent'] ?? '');
                            if ($item === null && isset($defaultItems[$r]['parent'])) {
                                $parent = (string) $defaultItems[$r]['parent'];
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
                                    <input name="label[<?php echo $e($code) ?>][<?php echo (int) $r ?>]" value="<?php echo $e($item->label ?? '') ?>" placeholder="<?php echo $e($t($handle === 'footer' ? 'admin.menus.placeholder_footer' : 'admin.menus.placeholder_item')) ?>">
                                </td>
                                <?php if ($handle === 'primary'): ?>
                                    <td>
                                        <?php if ($code === $defaultLocale): ?>
                                            <input name="parent[<?php echo (int) $r ?>]" value="<?php echo $e($parent) ?>" placeholder="<?php echo $e($t('admin.menus.placeholder_parent')) ?>" style="max-width:5rem">
                                        <?php else: ?>
                                            <span class="muted" data-menu-parent-display><?php echo $e($parent !== '' ? $parent : '—') ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php elseif ($code === $defaultLocale): ?>
                                    <input type="hidden" name="parent[<?php echo (int) $r ?>]" value="">
                                <?php endif; ?>
                                <td>
                                    <select name="page_id[<?php echo $e($code) ?>][<?php echo (int) $r ?>]">
                                        <option value=""><?php echo $e($t('admin.menus.external_url')) ?></option>
                                        <?php foreach ($localePages as $p): ?>
                                            <option value="<?php echo $e($p->id->value) ?>" <?php echo ($item !== null && $item->pageId !== null && $item->pageId->value === $p->id->value) ? 'selected' : '' ?>>
                                                <?php echo $e($p->title) ?> (<?php echo $e($p->path) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input name="url[<?php echo $e($code) ?>][<?php echo (int) $r ?>]" value="<?php echo $e($item->url ?? '') ?>" placeholder="/de oder https://…">
                                </td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <p class="muted" style="margin:0 0 .75rem">
            <?php echo $e($t('admin.menus.empty_rows')) ?>
            <?php if ($handle === 'primary'): ?>
                <?php echo $e($t('admin.menus.parent_hint')) ?>
            <?php endif; ?>
        </p>
        <p style="margin:0"><button type="submit"><?php echo $e($t('admin.common.save')) ?></button></p>
    </div>
</form>
<script src="<?php echo $e($basePath) ?>/assets/admin/menus-sortable.js" defer></script>
