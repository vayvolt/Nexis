<?php
/** @var \Nexis\Site\LocalePathResolver $paths */
/** @var list<array{groupId: string, primary: \Nexis\Content\Page, byLocale: array<string, \Nexis\Content\Page>}> $pageGroups */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$pageGroups = $pageGroups ?? [];
?>
<h1><?php echo $e($t('admin.pages.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.pages.list_intro')) ?></p>
<?php if (!empty($deleted)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.pages.deleted')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<p><?php if (!empty($canEditContent)): ?><a class="btn" href="<?php echo $e($basePath) ?>/admin/pages/new"><?php echo $e($t('admin.pages.new')) ?></a><?php endif; ?></p>

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
            <?php if ($pageGroups === []): ?>
                <tr><td colspan="5" class="muted"><?php echo $e($t('admin.pages.empty_all')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($pageGroups as $group): ?>
                <?php
                /** @var \Nexis\Content\Page $page */
                $page = $group['primary'];
                /** @var array<string, \Nexis\Content\Page> $byLocale */
                $byLocale = $group['byLocale'];
                $publicUrl = $paths->url($site, $page->locale, $page->path, $basePath);
                $statusClass = $page->isInReview ? 'badge-editor' : ($page->isPublished ? 'badge-admin' : ($page->isScheduled ? 'badge-editor' : ''));
                $when = $page->scheduledAt ? $page->scheduledAt->format('Y-m-d H:i') : '';
                $statusLabel = match (true) {
                    $page->isInReview => $t('admin.status.in_review'),
                    $page->isScheduled => $t('admin.status.scheduled_at', ['when' => $when]),
                    $page->isPublished && $page->scheduledAt !== null => $t('admin.status.live_update', ['when' => $when]),
                    $page->isPublished => $t('admin.status.published'),
                    default => $t('admin.status.draft'),
                };
                ?>
                <tr>
                    <td>
                        <strong><a href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/builder"><?php echo $e($page->title) ?></a></strong>
                    </td>
                    <td>
                        <?php
                        $localeSeoPartial = (!empty($adminViews) ? rtrim((string) $adminViews, '/\\') . '/admin' : dirname(__DIR__))
                            . '/partials/page_locale_seo.php';
                        require $localeSeoPartial;
                        ?>
                    </td>
                    <td><code><?php echo $e($page->path) ?></code></td>
                    <td><span class="badge <?php echo $e($statusClass) ?>"><?php echo $e($statusLabel) ?></span></td>
                    <td class="row-actions">
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/builder"><?php echo $e($t('admin.common.edit')) ?></a>
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/preview" target="_blank" rel="noopener"><?php echo $e($t('admin.common.preview')) ?></a>
                        <?php if ($page->isPublished): ?>
                            <a class="btn btn-small" href="<?php echo $e($publicUrl) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.common.public')) ?></a>
                        <?php endif; ?>
                        <?php
                        $localeLabels = [];
                        foreach (array_keys($byLocale) as $locCode) {
                            $localeLabels[] = strtoupper((string) $locCode);
                        }
                        $localeList = implode(', ', $localeLabels);
                        $localeCount = count($byLocale);
                        ?>
                        <?php if (!empty($canEditContent)): ?>
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/delete"
                              data-confirm-title="<?php echo $e($t('admin.pages.delete_title', ['title' => $page->title])) ?>"
                              data-confirm="<?php echo $e($t('admin.pages.delete_confirm', [
                                  'title' => $page->title,
                                  'count' => (string) $localeCount,
                                  'locales' => $localeList,
                              ])) ?>"
                              data-confirm-detail="<?php echo $e($t('admin.pages.delete_detail')) ?>"
                              data-confirm-ok="<?php echo $e($t('admin.common.delete')) ?>">
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
