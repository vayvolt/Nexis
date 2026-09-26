<?php
/** @var \Nexis\Site\LocalePathResolver $paths */
/** @var list<array{groupId: string, primary: \Nexis\Content\Page, byLocale: array<string, \Nexis\Content\Page>}> $productGroups */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$productGroups = $productGroups ?? [];
?>
<h1><?php echo $e($t('admin.catalog.title')) ?></h1>
<p class="muted">
    <?php echo $e($t('admin.catalog.list_intro')) ?>
    · <a href="<?php echo $e($archiveUrl) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.catalog.archive')) ?></a>
</p>
<?php if (!empty($created)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.catalog.created')) ?></p><?php endif; ?>

<p><a class="btn" href="<?php echo $e($basePath) ?>/admin/catalog/new"><?php echo $e($t('admin.catalog.new')) ?></a></p>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.common.title')) ?></th>
                <th><?php echo $e($t('admin.pages.col_locales')) ?></th>
                <th><?php echo $e($t('admin.common.path')) ?></th>
                <th><?php echo $e($t('admin.common.status')) ?></th>
                <th style="width:1%;white-space:nowrap"><?php echo $e($t('admin.common.actions')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($productGroups === []): ?>
                <tr><td colspan="5" class="muted"><?php echo $e($t('admin.catalog.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($productGroups as $group): ?>
                <?php
                /** @var \Nexis\Content\Page $product */
                $product = $group['primary'];
                /** @var array<string, \Nexis\Content\Page> $byLocale */
                $byLocale = $group['byLocale'];
                $publicUrl = $paths->url($site, $product->locale, $product->path, $basePath);
                $statusClass = $product->isInReview ? 'badge-editor' : ($product->isPublished ? 'badge-admin' : ($product->isScheduled ? 'badge-editor' : ''));
                $when = $product->scheduledAt ? $product->scheduledAt->format('Y-m-d H:i') : '';
                $statusLabel = match (true) {
                    $product->isInReview => $t('admin.status.in_review'),
                    $product->isScheduled => $t('admin.status.scheduled_at', ['when' => $when]),
                    $product->isPublished => $t('admin.status.published'),
                    default => $t('admin.status.draft'),
                };
                ?>
                <tr>
                    <td>
                        <strong><a href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($product->id->value) ?>/builder"><?php echo $e($product->title) ?></a></strong>
                    </td>
                    <td>
                        <?php require rtrim((string) ($adminViews ?? ''), '/\\') . '/admin/partials/page_locale_seo.php'; ?>
                    </td>
                    <td><code><?php echo $e($product->path) ?></code></td>
                    <td><span class="badge <?php echo $e($statusClass) ?>"><?php echo $e($statusLabel) ?></span></td>
                    <td class="row-actions">
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($product->id->value) ?>/builder"><?php echo $e($t('admin.common.edit')) ?></a>
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($product->id->value) ?>/preview" target="_blank" rel="noopener"><?php echo $e($t('admin.common.preview')) ?></a>
                        <?php if ($product->isPublished): ?>
                            <a class="btn btn-small" href="<?php echo $e($publicUrl) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.common.public')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
