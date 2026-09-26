<?php
/** @var \Nexis\Site\LocalePathResolver $paths */
/** @var list<array{groupId: string, primary: \Nexis\Content\Page, byLocale: array<string, \Nexis\Content\Page>}> $postGroups */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$postGroups = $postGroups ?? [];
?>
<h1><?php echo $e($t('admin.blog.title')) ?></h1>
<p class="muted">
    <?php echo $e($t('admin.blog.list_intro')) ?>
    · <a href="<?php echo $e($archiveUrl) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.blog.archive')) ?></a>
</p>
<?php if (!empty($created)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.blog.created')) ?></p><?php endif; ?>

<p><a class="btn" href="<?php echo $e($basePath) ?>/admin/blog/new"><?php echo $e($t('admin.blog.new')) ?></a></p>

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
            <?php if ($postGroups === []): ?>
                <tr><td colspan="5" class="muted"><?php echo $e($t('admin.blog.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($postGroups as $group): ?>
                <?php
                /** @var \Nexis\Content\Page $post */
                $post = $group['primary'];
                /** @var array<string, \Nexis\Content\Page> $byLocale */
                $byLocale = $group['byLocale'];
                $publicUrl = $paths->url($site, $post->locale, $post->path, $basePath);
                $statusClass = $post->isInReview ? 'badge-editor' : ($post->isPublished ? 'badge-admin' : ($post->isScheduled ? 'badge-editor' : ''));
                $when = $post->scheduledAt ? $post->scheduledAt->format('Y-m-d H:i') : '';
                $statusLabel = match (true) {
                    $post->isInReview => $t('admin.status.in_review'),
                    $post->isScheduled => $t('admin.status.scheduled_at', ['when' => $when]),
                    $post->isPublished => $t('admin.status.published'),
                    default => $t('admin.status.draft'),
                };
                ?>
                <tr>
                    <td>
                        <strong><a href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($post->id->value) ?>/builder"><?php echo $e($post->title) ?></a></strong>
                    </td>
                    <td>
                        <?php require rtrim((string) ($adminViews ?? ''), '/\\') . '/admin/partials/page_locale_seo.php'; ?>
                    </td>
                    <td><code><?php echo $e($post->path) ?></code></td>
                    <td><span class="badge <?php echo $e($statusClass) ?>"><?php echo $e($statusLabel) ?></span></td>
                    <td class="row-actions">
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($post->id->value) ?>/builder"><?php echo $e($t('admin.common.edit')) ?></a>
                        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($post->id->value) ?>/preview" target="_blank" rel="noopener"><?php echo $e($t('admin.common.preview')) ?></a>
                        <?php if ($post->isPublished): ?>
                            <a class="btn btn-small" href="<?php echo $e($publicUrl) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.common.public')) ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
