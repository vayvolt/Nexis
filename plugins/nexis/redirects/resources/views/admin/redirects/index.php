<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var array<string, mixed>|null $edit */
$edit = $edit ?? null;
$isEdit = is_array($edit);
$editId = $isEdit ? (string) ($edit['id'] ?? '') : '';
$editFrom = $isEdit ? (string) ($edit['from_path'] ?? '') : '';
$editTo = $isEdit ? (string) ($edit['to_url'] ?? '') : '';
$editLocale = $isEdit ? (string) ($edit['locale'] ?? '') : '';
$editCode = $isEdit ? (int) ($edit['status_code'] ?? 301) : 301;
?>
<h1><?php echo $e($t('admin.redirects.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.redirects.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.common.saved')) ?></p><?php endif; ?>
<?php if (isset($imported) && $imported !== null && $imported !== ''): ?>
    <p class="flash flash--success" role="status"><?php echo $e($t('admin.redirects.import_done', [
        'imported' => (string) (int) $imported,
        'skipped' => (string) (int) ($skipped ?? 0),
    ])) ?></p>
<?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<div class="card">
    <h2><?php echo $e($isEdit ? $t('admin.redirects.edit') : $t('admin.redirects.new')) ?></h2>
    <form id="redir-create-form" method="post" action="<?php echo $e($basePath) ?>/admin/redirects<?php echo $isEdit ? '/update' : '' ?>">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $e($editId) ?>">
        <?php endif; ?>
        <div class="row">
            <div>
                <label for="from_path"><?php echo $e($t('admin.redirects.from')) ?></label>
                <input id="from_path" name="from_path" required placeholder="/alt" value="<?php echo $e($editFrom) ?>">
            </div>
            <div>
                <label for="to_url"><?php echo $e($t('admin.redirects.to')) ?></label>
                <input id="to_url" name="to_url" required placeholder="/de oder https://…" value="<?php echo $e($editTo) ?>">
            </div>
        </div>
        <div class="row">
            <div>
                <label for="locale"><?php echo $e($t('admin.redirects.locale_optional')) ?></label>
                <input id="locale" name="locale" placeholder="<?php echo $e($t('admin.redirects.locale_placeholder')) ?>" value="<?php echo $e($editLocale) ?>">
            </div>
            <div>
                <label for="status_code"><?php echo $e($t('admin.common.status')) ?></label>
                <select id="status_code" name="status_code">
                    <?php foreach ([301 => '301 permanent', 302 => '302', 307 => '307', 308 => '308'] as $code => $label): ?>
                        <option value="<?php echo $code ?>"<?php echo $editCode === $code ? ' selected' : '' ?>><?php echo $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
    <div class="redir-actions">
        <button type="submit" class="btn" form="redir-create-form"><?php echo $e($isEdit ? $t('admin.common.save') : $t('admin.common.create')) ?></button>
        <?php if ($isEdit): ?>
            <a class="btn" href="<?php echo $e($basePath) ?>/admin/redirects"><?php echo $e($t('admin.common.cancel')) ?></a>
        <?php else: ?>
            <a class="btn" href="<?php echo $e($basePath) ?>/admin/redirects/export.csv"><?php echo $e($t('admin.redirects.export')) ?></a>
            <label class="btn" for="redir-import-toggle"><?php echo $e($t('admin.redirects.import_title')) ?></label>
        <?php endif; ?>
    </div>
    <?php if (!$isEdit): ?>
        <input type="checkbox" id="redir-import-toggle" class="redir-import-check"<?php echo !empty($showImport) ? ' checked' : '' ?>>
        <div class="redir-import__panel">
            <p class="muted"><?php echo $e($t('admin.redirects.import_hint')) ?></p>
            <pre class="muted" style="background:#f5f5f4;padding:.75rem 1rem;border-radius:6px;overflow:auto;font-size:.85rem">from_path,to_url,locale,status_code
/alt,/de/neu,,301
/old-en,/en/new,en,301</pre>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/redirects/import" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <label for="csv"><?php echo $e($t('admin.redirects.import_file')) ?></label>
                <input id="csv" type="file" name="csv" accept=".csv,text/csv,text/plain" required>
                <p style="margin-top:.75rem">
                    <button type="submit" class="btn"><?php echo $e($t('admin.redirects.import_submit')) ?></button>
                </p>
            </form>
        </div>
    <?php endif; ?>
</div>
<style>
.redir-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin: 1rem 0 0; }
.redir-actions > .btn,
.redir-actions > label.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    min-height: 2.35rem;
    margin: 0;
    line-height: 1.2;
    cursor: pointer;
}
.redir-import-check {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.redir-import-check:not(:checked) + .redir-import__panel { display: none; }
.redir-import__panel {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e7e5e4;
}
</style>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.redirects.from_col')) ?></th>
                <th><?php echo $e($t('admin.redirects.to_col')) ?></th>
                <th>Locale</th>
                <th>Code</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($redirects === []): ?>
                <tr><td colspan="5" class="muted"><?php echo $e($t('admin.redirects.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($redirects as $row): ?>
                <?php $rowId = (string) $row['id']; ?>
                <tr<?php echo $editId === $rowId ? ' class="is-editing"' : '' ?>>
                    <td><code><?php echo $e((string) $row['from_path']) ?></code></td>
                    <td><code><?php echo $e((string) $row['to_url']) ?></code></td>
                    <td><?php echo $e((string) ($row['locale'] ?? $t('admin.common.empty_dash'))) ?></td>
                    <td><?php echo $e((string) $row['status_code']) ?></td>
                    <td class="row-actions">
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/redirects?edit=<?php echo $e(rawurlencode($rowId)) ?>"><?php echo $e($t('admin.common.edit')) ?></a>
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/redirects/delete">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <input type="hidden" name="id" value="<?php echo $e($rowId) ?>">
                            <button type="submit" class="btn-small btn-danger"><?php echo $e($t('admin.common.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h2><?php echo $e($t('admin.redirects.not_found')) ?></h2>
<p class="muted"><?php echo $e($t('admin.redirects.not_found_hint')) ?></p>
<?php if (($notFoundHits ?? []) !== []): ?>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/redirects/not-found/clear" data-confirm="<?php echo $e($t('admin.redirects.clear_confirm')) ?>" style="margin-bottom:1rem">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <button type="submit" class="btn-small btn-danger"><?php echo $e($t('admin.redirects.clear_log')) ?></button>
    </form>
<?php endif; ?>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.common.path')) ?></th>
                <th>Locale</th>
                <th><?php echo $e($t('admin.redirects.hits')) ?></th>
                <th><?php echo $e($t('admin.redirects.last_seen')) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (($notFoundHits ?? []) === []): ?>
                <tr><td colspan="5" class="muted"><?php echo $e($t('admin.redirects.empty_hits')) ?></td></tr>
            <?php endif; ?>
            <?php foreach (($notFoundHits ?? []) as $hit): ?>
                <?php
                $hitPath = (string) ($hit['path'] ?? '');
                $hitLocale = (string) ($hit['locale'] ?? '');
                ?>
                <tr>
                    <td><code><?php echo $e($hitPath) ?></code></td>
                    <td><?php echo $e($hitLocale !== '' ? $hitLocale : $t('admin.common.empty_dash')) ?></td>
                    <td><?php echo (int) ($hit['hit_count'] ?? 0) ?></td>
                    <td class="muted"><?php echo $e((string) ($hit['last_seen_at'] ?? '')) ?></td>
                    <td class="row-actions">
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/redirects">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <input type="hidden" name="from_path" value="<?php echo $e($hitPath) ?>">
                            <input type="hidden" name="locale" value="<?php echo $e($hitLocale) ?>">
                            <input type="hidden" name="to_url" value="<?php echo $e($basePath !== '' ? $basePath . '/' : '/') ?>">
                            <input type="hidden" name="status_code" value="301">
                            <input type="hidden" name="not_found_id" value="<?php echo $e((string) $hit['id']) ?>">
                            <button type="submit" class="btn-small"><?php echo $e($t('admin.redirects.make')) ?></button>
                        </form>
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/redirects/not-found/delete">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <input type="hidden" name="id" value="<?php echo $e((string) $hit['id']) ?>">
                            <button type="submit" class="btn-small btn-danger"><?php echo $e($t('admin.redirects.remove')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
