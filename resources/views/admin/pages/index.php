<?php
/** @var \Nexis\Site\LocalePathResolver $paths */
/** @var list<array{groupId: string, primary: \Nexis\Content\Page, byLocale: array<string, \Nexis\Content\Page>}> $pageGroups */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$pageGroups = $pageGroups ?? [];
$filterQ = (string) ($filterQ ?? '');
$filterStatus = (string) ($filterStatus ?? '');
$locale = (string) ($locale ?? $site->defaultLocale);
$hasFilters = $filterQ !== '' || $filterStatus !== '';
?>
<h1><?php echo $e($t('admin.pages.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.pages.list_intro')) ?></p>
<?php if (!empty($deleted)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.pages.deleted')) ?></p><?php endif; ?>
<?php if (!empty($restored)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.pages.restored')) ?></p><?php endif; ?>
<?php if (!empty($legalSeeded)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.pages.legal_seeded', ['count' => (string) ($legalCreated ?? '0')])) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>
<?php
$legalGaps = is_array($legalGaps ?? null) ? $legalGaps : [];
if ($legalGaps !== [] && !empty($canSeedLegal)):
    $gapLabels = array_map(
        static fn (array $g): string => (string) ($g['title'] ?? '') . ' (' . (string) ($g['locale'] ?? '') . ')',
        $legalGaps,
    );
?>
<p class="flash" role="status">
    <?php echo $e($t('admin.pages.legal_missing', ['pages' => implode(', ', $gapLabels)])) ?>
</p>
<form method="post" action="<?php echo $e($basePath) ?>/admin/pages/seed-legal" style="margin:0 0 1rem">
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
    <button type="submit" class="btn"><?php echo $e($t('admin.pages.legal_seed')) ?></button>
</form>
<?php endif; ?>

<p>
    <?php if (!empty($canEditContent)): ?><a class="btn" href="<?php echo $e($basePath) ?>/admin/pages/new"><?php echo $e($t('admin.pages.new')) ?></a><?php endif; ?>
    <a class="btn" href="<?php echo $e($basePath) ?>/admin/pages/trash"><?php echo $e($t('admin.pages.trash_link', ['count' => (string) (int) ($trashCount ?? 0)])) ?></a>
</p>

<div class="card">
    <form method="get" action="<?php echo $e($basePath) ?>/admin/pages" class="form-grid" style="margin:0;align-items:end">
        <div>
            <label for="pages-q"><?php echo $e($t('admin.common.search')) ?></label>
            <input id="pages-q" name="q" type="search" value="<?php echo $e($filterQ) ?>" placeholder="<?php echo $e($t('admin.pages.filter_q_placeholder')) ?>">
        </div>
        <div>
            <label for="pages-status"><?php echo $e($t('admin.common.status')) ?></label>
            <select id="pages-status" name="status">
                <option value=""><?php echo $e($t('admin.common.all')) ?></option>
                <option value="draft" <?php echo $filterStatus === 'draft' ? 'selected' : '' ?>><?php echo $e($t('admin.status.draft')) ?></option>
                <option value="in_review" <?php echo $filterStatus === 'in_review' ? 'selected' : '' ?>><?php echo $e($t('admin.status.in_review')) ?></option>
                <option value="scheduled" <?php echo $filterStatus === 'scheduled' ? 'selected' : '' ?>><?php echo $e($t('admin.status.scheduled')) ?></option>
                <option value="published" <?php echo $filterStatus === 'published' ? 'selected' : '' ?>><?php echo $e($t('admin.status.published')) ?></option>
            </select>
        </div>
        <div>
            <label for="pages-locale"><?php echo $e($t('admin.pages.filter_locale')) ?></label>
            <select id="pages-locale" name="locale">
                <?php foreach ($site->enabledLocales() as $loc): ?>
                    <option value="<?php echo $e($loc->locale) ?>" <?php echo $locale === $loc->locale ? 'selected' : '' ?>>
                        <?php echo $e($loc->label !== '' ? $loc->label : strtoupper($loc->locale)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit"><?php echo $e($t('admin.common.filter')) ?></button>
            <?php if ($hasFilters): ?>
                <a class="btn btn-muted" href="<?php echo $e($basePath) ?>/admin/pages?locale=<?php echo $e($locale) ?>" style="margin-left:.35rem"><?php echo $e($t('admin.common.reset')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($pageGroups === []): ?>
    <div class="empty-state" role="status">
        <p><?php echo $e($hasFilters ? $t('admin.pages.empty_filtered') : $t('admin.pages.empty_all')) ?></p>
        <?php if ($hasFilters): ?>
            <p class="empty-state__hint"><a href="<?php echo $e($basePath) ?>/admin/pages?locale=<?php echo $e($locale) ?>"><?php echo $e($t('admin.pages.filter_clear')) ?></a></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card" style="padding:0">
        <div class="table-wrap" style="margin:0;border:0;border-radius:0">
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
                        $depth = $page->path === '/' ? 0 : substr_count(trim($page->path, '/'), '/');
                        $indentPx = max(0, $depth) * 18;
                        ?>
                        <tr>
                            <td style="<?php echo $indentPx > 0 ? 'padding-left:' . (12 + $indentPx) . 'px' : '' ?>">
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
    </div>
<?php endif; ?>
