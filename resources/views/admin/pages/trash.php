<?php
/** @var list<array{groupId: string, primary: \Nexis\Content\TrashEntry, byLocale: array<string, \Nexis\Content\TrashEntry>}> $trashGroups */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$trashGroups = $trashGroups ?? [];
?>
<h1><?php echo $e($t('admin.pages.trash_title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.pages.trash_intro')) ?></p>
<div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:center;margin:0 0 1rem">
    <a href="<?php echo $e($basePath) ?>/admin/pages"><?php echo $e($t('admin.pages.back_to_list')) ?></a>
    <?php if (!empty($canEditContent) && $trashGroups !== []): ?>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/trash/empty"
              data-confirm-title="<?php echo $e($t('admin.pages.empty_trash_title')) ?>"
              data-confirm="<?php echo $e($t('admin.pages.empty_trash_confirm', ['count' => (string) count($trashGroups)])) ?>"
              data-confirm-detail="<?php echo $e($t('admin.pages.empty_trash_detail')) ?>"
              data-confirm-ok="<?php echo $e($t('admin.pages.empty_trash')) ?>">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.pages.empty_trash')) ?></button>
        </form>
    <?php endif; ?>
</div>
<?php if (!empty($restored)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.pages.restored')) ?></p><?php endif; ?>
<?php if (!empty($purged)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.pages.purged')) ?></p><?php endif; ?>
<?php if (!empty($emptied)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.pages.trash_emptied')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.common.title')) ?></th>
                <th><?php echo $e($t('admin.pages.col_locales')) ?></th>
                <th><?php echo $e($t('admin.pages.trash_col_path')) ?></th>
                <th><?php echo $e($t('admin.pages.trash_col_deleted')) ?></th>
                <th style="width:1%;white-space:nowrap"><?php echo $e($t('admin.common.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($trashGroups === []): ?>
                <tr><td colspan="5" class="muted"><?php echo $e($t('admin.pages.trash_empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($trashGroups as $group): ?>
                <?php
                $entry = $group['primary'];
                $page = $entry->page;
                $byLocale = $group['byLocale'];
                $restorePath = $entry->restorePath ?? '—';
                $deletedWhen = $entry->deletedAt->format('d.m.Y H:i');
                $localeLabels = [];
                foreach (array_keys($byLocale) as $locCode) {
                    $localeLabels[] = strtoupper((string) $locCode);
                }
                $localeList = implode(', ', $localeLabels);
                $localeCount = count($byLocale);
                ?>
                <tr>
                    <td><strong><?php echo $e($page->title) ?></strong></td>
                    <td>
                        <?php echo $e($localeList !== '' ? $localeList : strtoupper($page->locale)) ?>
                        <?php if ($localeCount > 1): ?>
                            <span class="muted">(<?php echo $e((string) $localeCount) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo $e($restorePath) ?></code></td>
                    <td><?php echo $e($deletedWhen) ?></td>
                    <td class="row-actions">
                        <?php if (!empty($canEditContent)): ?>
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/restore"
                              style="display:inline">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <button type="submit" class="btn btn-small"><?php echo $e($t('admin.pages.restore')) ?></button>
                        </form>
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/purge"
                              data-confirm-title="<?php echo $e($t('admin.pages.purge_title', ['title' => $page->title])) ?>"
                              data-confirm="<?php echo $e($t('admin.pages.purge_confirm', [
                                  'title' => $page->title,
                                  'count' => (string) $localeCount,
                                  'locales' => $localeList,
                              ])) ?>"
                              data-confirm-detail="<?php echo $e($t('admin.pages.purge_detail')) ?>"
                              data-confirm-ok="<?php echo $e($t('admin.pages.purge')) ?>">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.pages.purge')) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
