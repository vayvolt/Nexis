<link rel="stylesheet" href="<?php echo $e($basePath) ?>/assets/admin/builder.css">
<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$canPublish = !empty($canPublish);
$canSubmitReview = !empty($canSubmitReview);
$canEditContent = ($canEditContent ?? true) !== false;
$canEditSeo = ($canEditSeo ?? true) !== false;
$statusLabel = match (true) {
    $page->isInReview => $t('admin.status.in_review'),
    $page->isScheduled => $t('admin.status.scheduled_title'),
    $page->isPublished => $t('admin.status.published'),
    default => $t('admin.status.draft'),
};
$statusClass = $page->isInReview ? 'badge-editor' : ($page->isPublished ? 'badge-admin' : ($page->isScheduled ? 'badge-editor' : ''));
$blockCatalog = array_map(static function (array $item) use ($t): array {
    $type = (string) ($item['type'] ?? '');
    if ($type !== '') {
        $key = 'admin.builder.block.' . str_replace('/', '.', $type);
        $item['label'] = $t($key, [], (string) ($item['label'] ?? $type));
    }

    return $item;
}, is_array($blockCatalog ?? null) ? $blockCatalog : []);
?>

<div class="nx-builder-shell">
    <header class="nx-builder-top">
        <div class="nx-builder-top__lead">
            <a class="nx-builder-back" href="<?php echo $e($basePath) ?>/admin/pages"><?php echo $e($t('admin.builder.back_pages')) ?></a>
            <h1 class="nx-builder-heading"><?php echo $e($page->title) ?></h1>
            <span class="badge <?php echo $e($statusClass) ?>"><?php echo $e($statusLabel) ?></span>
        </div>
        <div class="nx-builder-top__aside">
            <?php
            $localeSurface = 'builder';
            require __DIR__ . DIRECTORY_SEPARATOR . '_locale_switch.php';
            ?>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/preview" target="_blank" rel="noopener"><?php echo $e($t('admin.builder.preview')) ?></a>
            <?php if (!empty($signedPreview)): ?>
                <a class="btn btn-small" href="<?php echo $e($signedPreview) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.builder.signed_preview')) ?></a>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($error !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>
    <?php if (!empty($_GET['saved'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.saved')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['published'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.published')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['review_submitted'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.review_submitted')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['review_rejected'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.review_rejected')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['unpublished'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.unpublished')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['scheduled'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.scheduled')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['unpublish_scheduled'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.unpublish_scheduled')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['reverted'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.reverted')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['pattern_saved'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.pattern_saved')) ?></p><?php endif; ?>
    <?php if (!empty($_GET['editorial'])): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.builder.editorial_saved')) ?></p><?php endif; ?>
    <?php if (is_string($_GET['editorial_error'] ?? null) && $_GET['editorial_error'] !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e((string) $_GET['editorial_error']) ?></p><?php endif; ?>
    <?php if (is_string($_GET['pattern_error'] ?? null) && $_GET['pattern_error'] !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e((string) $_GET['pattern_error']) ?></p><?php endif; ?>

    <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/builder" id="nx-builder-form">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="document_hash" value="<?php echo $e($revision->documentHash) ?>">

        <section class="nx-builder-section" aria-labelledby="nx-page-settings-title">
            <div class="nx-builder-section__head">
                <h2 id="nx-page-settings-title"><?php echo $e($t('admin.builder.section_page')) ?></h2>
                <p class="muted"><?php echo $e($t('admin.builder.section_page_hint')) ?></p>
            </div>
            <div class="nx-builder-meta">
                <div class="row">
                    <div>
                        <label for="page-title"><?php echo $e($t('admin.common.title')) ?></label>
                        <input id="page-title" name="title" <?php echo $canEditContent ? 'required' : 'readonly' ?> maxlength="190" value="<?php echo $e($page->title) ?>"<?php echo $canEditContent ? '' : ' aria-readonly="true"' ?>>
                    </div>
                    <div>
                        <label for="page-slug"><?php echo $e($t('admin.common.slug')) ?></label>
                        <input id="page-slug" name="slug" maxlength="190" value="<?php echo $e($page->slug) ?>" placeholder="home"<?php echo $canEditContent ? '' : ' readonly aria-readonly="true"' ?>>
                    </div>
                </div>
                <?php
                /** @var array{score: int, checks: list<array{id: string, ok: bool, weight: int}>} $seoAnalysis */
                $seoAnalysis = $seoAnalysis ?? \Nexis\Content\PageSeoAnalysis::analyze($page);
                $seoPreviewUrl = $seoPreviewUrl ?? '';
                $seoScore = (int) $seoAnalysis['score'];
                $checkLabels = [
                    'keyword_set' => $t('admin.builder.seo_check.keyword_set'),
                    'keyword_in_title' => $t('admin.builder.seo_check.keyword_in_title'),
                    'keyword_in_description' => $t('admin.builder.seo_check.keyword_in_description'),
                    'title_length' => $t('admin.builder.seo_check.title_length'),
                    'description_length' => $t('admin.builder.seo_check.description_length'),
                    'robots_index' => $t('admin.builder.seo_check.robots_index'),
                ];
                $seoOpen = !$canEditContent && $canEditSeo;
                ?>
                <details class="nx-builder-fold nx-builder-fold--seo"<?php echo $seoOpen ? ' open' : '' ?>>
                    <summary>
                        <span class="nx-builder-fold__chevron" aria-hidden="true"></span>
                        <span class="nx-builder-fold__label"><?php echo $e($t('admin.builder.seo_details')) ?></span>
                        <span class="nx-seo-summary-score<?php echo $seoScore < 40 ? ' is-low' : ($seoScore < 70 ? ' is-mid' : '') ?>" id="nx-seo-summary-score" data-score="<?php echo $seoScore ?>"><?php echo $e($t('admin.builder.seo_score_badge', ['score' => (string) $seoScore])) ?></span>
                    </summary>
                    <?php if ($canEditSeo): ?>
                    <label for="focus_keyword"><?php echo $e($t('admin.pages.focus_keyword')) ?></label>
                    <input id="focus_keyword" name="focus_keyword" maxlength="120" value="<?php echo $e($page->focusKeyword ?? '') ?>" placeholder="<?php echo $e($t('admin.pages.focus_keyword_placeholder')) ?>" data-seo-field="keyword">
                    <p class="muted" style="margin:.25rem 0 .65rem;font-size:.85rem"><?php echo $e($t('admin.pages.focus_keyword_hint')) ?></p>
                    <div class="row">
                        <div>
                            <label for="meta_title"><?php echo $e($t('admin.pages.seo_title')) ?></label>
                            <input id="meta_title" name="meta_title" maxlength="190" value="<?php echo $e($page->metaTitle ?? '') ?>" data-seo-field="meta_title">
                        </div>
                        <div>
                            <label for="meta_description"><?php echo $e($t('admin.pages.seo_desc')) ?></label>
                            <input id="meta_description" name="meta_description" maxlength="320" value="<?php echo $e($page->metaDescription ?? '') ?>" data-seo-field="meta_description">
                        </div>
                    </div>
                    <label for="robots"><?php echo $e($t('admin.pages.robots')) ?></label>
                    <select id="robots" name="robots" data-seo-field="robots">
                        <?php
                        $robots = $page->robots ?? 'index,follow';
                        foreach (['index,follow' => 'index, follow', 'noindex,follow' => 'noindex, follow', 'index,nofollow' => 'index, nofollow', 'noindex,nofollow' => 'noindex, nofollow'] as $value => $label):
                            ?>
                            <option value="<?php echo $e($value) ?>" <?php echo $robots === $value ? 'selected' : '' ?>><?php echo $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <p class="muted"><?php echo $e($t('admin.builder.seo_readonly_hint')) ?></p>
                    <?php endif; ?>

                    <div class="nx-seo-panel" id="nx-seo-panel" data-page-title="<?php echo $e($page->title) ?>" data-preview-url="<?php echo $e($seoPreviewUrl) ?>">
                        <div class="nx-seo-score">
                            <strong id="nx-seo-score-value"><?php echo $seoScore ?></strong>
                            <span class="muted"><?php echo $e($t('admin.builder.seo_score_label')) ?></span>
                        </div>
                        <ul class="nx-seo-checks" id="nx-seo-checks">
                            <?php foreach ($seoAnalysis['checks'] as $check): ?>
                                <li data-check="<?php echo $e($check['id']) ?>" class="<?php echo $check['ok'] ? 'is-ok' : 'is-bad' ?>">
                                    <span class="nx-seo-check-mark" aria-hidden="true"><?php echo $check['ok'] ? '✓' : '·' ?></span>
                                    <?php echo $e($checkLabels[$check['id']] ?? $check['id']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="nx-seo-previews">
                            <p class="muted" style="margin:0 0 .35rem;font-size:.8rem"><?php echo $e($t('admin.builder.seo_preview_search')) ?></p>
                            <div class="nx-seo-serp" aria-live="polite">
                                <div class="nx-seo-serp__url" id="nx-seo-serp-url"><?php echo $e($seoPreviewUrl !== '' ? $seoPreviewUrl : '…') ?></div>
                                <div class="nx-seo-serp__title" id="nx-seo-serp-title"><?php echo $e($page->documentTitle) ?></div>
                                <div class="nx-seo-serp__desc" id="nx-seo-serp-desc"><?php echo $e($page->documentDescription !== '' ? $page->documentDescription : $t('admin.builder.seo_preview_no_desc')) ?></div>
                            </div>
                            <p class="muted" style="margin:.75rem 0 .35rem;font-size:.8rem"><?php echo $e($t('admin.builder.seo_preview_social')) ?></p>
                            <div class="nx-seo-og" aria-live="polite">
                                <div class="nx-seo-og__domain" id="nx-seo-og-domain"><?php echo $e(parse_url($seoPreviewUrl, PHP_URL_HOST) ?: ($site->primaryDomain ?? 'example.test')) ?></div>
                                <div class="nx-seo-og__title" id="nx-seo-og-title"><?php echo $e($page->documentTitle) ?></div>
                                <div class="nx-seo-og__desc" id="nx-seo-og-desc"><?php echo $e($page->documentDescription !== '' ? $page->documentDescription : $t('admin.builder.seo_preview_no_desc')) ?></div>
                            </div>
                        </div>
                    </div>
                </details>
            </div>
        </section>

        <?php if ($canEditContent): ?>
        <section class="nx-builder-section" aria-labelledby="nx-content-title">
            <div class="nx-builder-section__head">
                <h2 id="nx-content-title"><?php echo $e($t('admin.builder.section_content')) ?></h2>
                <p class="muted"><?php echo $e($t('admin.builder.section_content_hint')) ?></p>
            </div>
            <div class="nx-builder">
                <aside class="nx-builder-panel">
                    <h3><?php echo $e($t('admin.builder.blocks')) ?></h3>
                    <p class="muted nx-builder-panel__hint"><?php echo $e($t('admin.builder.blocks_hint')) ?></p>
                    <div id="nx-palette"></div>
                </aside>
                <section class="nx-builder-panel nx-canvas" id="nx-canvas" aria-label="<?php echo $e($t('admin.builder.canvas_label')) ?>"></section>
                <aside class="nx-builder-panel nx-props-wrap">
                    <h3><?php echo $e($t('admin.builder.props_heading')) ?></h3>
                    <div id="nx-props">
                        <p class="muted"><?php echo $e($t('admin.builder.select_block')) ?></p>
                    </div>
                </aside>
            </div>
            <textarea id="nx-document" name="document" hidden><?php echo $e($documentJson) ?></textarea>
            <label class="nx-builder-note" for="message"><?php echo $e($t('admin.builder.message')) ?></label>
            <input id="message" name="message" placeholder="<?php echo $e($t('admin.builder.message_placeholder')) ?>">
        </section>
        <?php else: ?>
            <textarea id="nx-document" name="document" hidden><?php echo $e($documentJson) ?></textarea>
            <p class="muted"><?php echo $e($t('admin.builder.seo_only_hint')) ?></p>
        <?php endif; ?>

        <div class="nx-builder-actions">
            <div class="nx-builder-actions__primary">
                <button type="submit" class="nx-builder-save"><?php echo $e($t('admin.common.save')) ?></button>
                <?php if ($canSubmitReview && !$page->isInReview): ?>
                    <button type="submit" class="btn" form="nx-submit-review-form"><?php echo $e($t('admin.common.submit_review')) ?></button>
                <?php endif; ?>
                <?php if ($canPublish && $page->isInReview): ?>
                    <button type="submit" form="nx-publish-form"><?php echo $e($t('admin.common.approve_publish')) ?></button>
                    <button type="submit" class="btn nx-btn-muted" form="nx-reject-review-form"><?php echo $e($t('admin.common.reject_review')) ?></button>
                <?php elseif ($canPublish): ?>
                    <button type="submit" form="nx-publish-form"><?php echo $e($t('admin.common.publish')) ?></button>
                <?php endif; ?>
                <?php if ($canPublish && ($page->publishedSnapshotId !== null || $page->isPublished || $page->isScheduled)): ?>
                    <button type="submit" class="btn-danger" form="nx-unpublish-form"><?php echo $e($t('admin.common.unpublish')) ?></button>
                <?php endif; ?>
            </div>
            <p class="muted nx-builder-actions__hint"><?php echo $e($t('admin.builder.actions_hint')) ?></p>
        </div>
    </form>

    <?php if ($canSubmitReview && !$page->isInReview): ?>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/submit-review" id="nx-submit-review-form" hidden>
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        </form>
    <?php endif; ?>
    <?php if ($canPublish): ?>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/publish" id="nx-publish-form" hidden>
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        </form>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/reject-review" id="nx-reject-review-form" hidden>
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        </form>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/unpublish" id="nx-unpublish-form" hidden
              data-confirm-title="<?php echo $e($t('admin.builder.unpublish_confirm_title')) ?>"
              data-confirm="<?php echo $e($t('admin.builder.unpublish_confirm')) ?>"
              data-confirm-ok="<?php echo $e($t('admin.common.unpublish')) ?>">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        </form>
    <?php endif; ?>

    <?php if ($canEditContent): ?>
    <?php
    /** @var list<\Nexis\Content\PageEditorialItem> $editorialItems */
    $editorialItems = $editorialItems ?? [];
    $openTasks = 0;
    foreach ($editorialItems as $edItem) {
        if ($edItem->isTask && !$edItem->isDone) {
            $openTasks++;
        }
    }
    ?>
    <section class="nx-builder-section nx-builder-section--fold" id="nx-editorial">
        <details class="nx-builder-fold nx-builder-fold--card" <?php echo ($editorialItems !== [] || !empty($_GET['editorial']) || !empty($_GET['editorial_error'])) ? 'open' : '' ?>>
            <summary>
                <?php echo $e($t('admin.builder.section_editorial')) ?>
                <?php if ($openTasks > 0): ?>
                    <span class="badge badge-editor"><?php echo $e($t('admin.builder.editorial_open_tasks', ['count' => (string) $openTasks])) ?></span>
                <?php endif; ?>
            </summary>
            <p class="muted"><?php echo $e($t('admin.builder.section_editorial_hint')) ?></p>

            <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/editorial" class="nx-builder-editorial-form">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <label for="editorial_body"><?php echo $e($t('admin.builder.editorial_body')) ?></label>
                <textarea id="editorial_body" name="body" rows="3" maxlength="4000" required placeholder="<?php echo $e($t('admin.builder.editorial_body_placeholder')) ?>"></textarea>
                <div class="nx-builder-inline" style="margin-top:.55rem">
                    <select name="kind" aria-label="<?php echo $e($t('admin.builder.editorial_kind')) ?>">
                        <option value="comment"><?php echo $e($t('admin.builder.editorial_kind_comment')) ?></option>
                        <option value="task"><?php echo $e($t('admin.builder.editorial_kind_task')) ?></option>
                    </select>
                    <button type="submit" class="btn-small"><?php echo $e($t('admin.builder.editorial_add')) ?></button>
                </div>
            </form>

            <?php if ($editorialItems === []): ?>
                <p class="muted" style="margin-top:.85rem"><?php echo $e($t('admin.builder.editorial_empty')) ?></p>
            <?php else: ?>
                <ul class="nx-builder-editorial-list">
                    <?php foreach ($editorialItems as $item): ?>
                        <li class="nx-builder-editorial-item<?php echo $item->isDone ? ' is-done' : '' ?>">
                            <div class="nx-builder-editorial-item__meta">
                                <span class="badge"><?php echo $e($item->isTask
                                    ? ($item->isDone ? $t('admin.builder.editorial_kind_task_done') : $t('admin.builder.editorial_kind_task'))
                                    : $t('admin.builder.editorial_kind_comment')) ?></span>
                                <span class="muted">
                                    <?php echo $e($item->authorName ?? $t('admin.builder.editorial_unknown_author')) ?>
                                    · <?php echo $e($item->createdAt->format('Y-m-d H:i')) ?>
                                </span>
                            </div>
                            <p class="nx-builder-editorial-item__body"><?php echo nl2br($e($item->body)) ?></p>
                            <div class="nx-builder-inline">
                                <?php if ($item->isTask): ?>
                                    <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/editorial/<?php echo $e($item->id->value) ?>/toggle">
                                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                        <button type="submit" class="btn-small"><?php echo $e($item->isDone
                                            ? $t('admin.builder.editorial_reopen')
                                            : $t('admin.builder.editorial_done')) ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($item->createdBy === ($user->id->value ?? '') || !empty($canPublish)): ?>
                                    <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/editorial/<?php echo $e($item->id->value) ?>/delete"
                                          data-confirm="<?php echo $e($t('admin.builder.editorial_delete_confirm')) ?>"
                                          data-confirm-ok="<?php echo $e($t('admin.common.delete')) ?>">
                                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                        <button type="submit" class="btn-small btn-danger"><?php echo $e($t('admin.common.delete')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </details>
    </section>
    <?php endif; ?>

    <?php if ($canPublish): ?>
        <section class="nx-builder-section nx-builder-section--fold">
            <details class="nx-builder-fold nx-builder-fold--card" <?php echo ($page->isScheduled || $page->unpublishAt) ? 'open' : '' ?>>
                <summary><?php echo $e($t('admin.builder.section_schedule')) ?></summary>
                <p class="muted"><?php echo $e($t('admin.builder.section_schedule_hint')) ?></p>
                <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/schedule" class="nx-builder-schedule">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                    <label for="scheduled_at"><?php echo $e($t('admin.pages.schedule_label')) ?></label>
                    <div class="nx-builder-inline">
                        <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                               value="<?php echo $e(($page->scheduledAt?->format('Y-m-d\TH:i')) ?? '') ?>" required>
                        <button type="submit" class="btn-small"><?php echo $e($t('admin.common.schedule')) ?></button>
                    </div>
                    <?php if ($page->isScheduled && $page->scheduledAt): ?>
                        <p class="muted"><?php echo $e($t('admin.builder.schedule_for', ['when' => $page->scheduledAt->format('Y-m-d H:i')])) ?></p>
                    <?php endif; ?>
                </form>
                <?php if ($page->publishedSnapshotId !== null): ?>
                    <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/schedule-unpublish" class="nx-builder-schedule">
                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                        <label for="unpublish_at"><?php echo $e($t('admin.pages.unpublish_schedule_label')) ?></label>
                        <div class="nx-builder-inline">
                            <input id="unpublish_at" type="datetime-local" name="unpublish_at"
                                   value="<?php echo $e(($page->unpublishAt?->format('Y-m-d\TH:i')) ?? '') ?>">
                            <button type="submit" class="btn-small"><?php echo $e($t('admin.common.schedule_unpublish')) ?></button>
                            <?php if ($page->unpublishAt): ?>
                                <button type="submit" name="clear_unpublish" value="1" class="btn-small nx-btn-muted"><?php echo $e($t('admin.common.clear_unpublish')) ?></button>
                            <?php endif; ?>
                        </div>
                        <?php if ($page->unpublishAt): ?>
                            <p class="muted"><?php echo $e($t('admin.builder.unpublish_for', ['when' => $page->unpublishAt->format('Y-m-d H:i')])) ?></p>
                        <?php endif; ?>
                        <p class="muted"><?php echo $e($t('admin.pages.unpublish_schedule_hint')) ?></p>
                    </form>
                <?php endif; ?>
            </details>
        </section>
    <?php endif; ?>

    <section class="nx-builder-section nx-builder-section--fold">
        <details class="nx-builder-fold nx-builder-fold--card">
            <summary><?php echo $e($t('admin.builder.section_advanced')) ?></summary>
            <p class="muted"><?php echo $e($t('admin.builder.section_advanced_hint')) ?></p>
            <div class="nx-json-toggle">
                <label for="nx-document-visible"><?php echo $e($t('admin.builder.json')) ?></label>
                <p class="muted"><?php echo $e($t('admin.builder.json_hint')) ?></p>
                <textarea id="nx-document-visible" rows="10"><?php echo $e($documentJson) ?></textarea>
            </div>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/revert" class="nx-builder-revisions">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <label for="revision_id"><?php echo $e($t('admin.builder.revert_to')) ?></label>
                <div class="nx-builder-inline">
                    <select id="revision_id" name="revision_id">
                        <?php foreach ($revisions as $rev): ?>
                            <option value="<?php echo $e($rev->id->value) ?>">
                                <?php echo $e($rev->createdAt->format('Y-m-d H:i:s')) ?>
                                · <?php echo $e(substr($rev->documentHash, 0, 8)) ?>
                                <?php if ($rev->message): ?> · <?php echo $e($rev->message) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-small"><?php echo $e($t('admin.builder.revert')) ?></button>
                </div>
            </form>
        </details>
    </section>
</div>

<script type="application/json" id="nx-builder-config"><?php echo json_encode([
    'catalog' => $blockCatalog,
    'patterns' => $patternCatalog ?? [],
    'media' => $mediaCatalog ?? [],
    'basePath' => $basePath,
    'csrf' => (string) $csrf,
    'savePatternUrl' => $savePatternUrl ?? ($basePath . '/admin/patterns'),
    'documentInputId' => 'nx-document',
    'documentVisibleId' => 'nx-document-visible',
    'formId' => 'nx-builder-form',
    'canvasId' => 'nx-canvas',
    'paletteId' => 'nx-palette',
    'propsId' => 'nx-props',
    'i18n' => [
        'selectBlock' => $t('admin.builder.select_block'),
        'remove' => $t('admin.builder.remove'),
        'moveUp' => $t('admin.builder.move_up'),
        'moveDown' => $t('admin.builder.move_down'),
        'dropHere' => $t('admin.builder.drop_here'),
        'dropHint' => $t('admin.builder.drop_hint'),
        'noProps' => $t('admin.builder.no_props'),
        'noBlocks' => $t('admin.builder.no_blocks'),
        'addToSection' => $t('admin.builder.add_to_section'),
        'noImage' => $t('admin.builder.no_image'),
        'noMedia' => $t('admin.builder.no_media'),
        'noRoot' => $t('admin.builder.no_root'),
        'emptyText' => $t('admin.builder.empty_text'),
        'image' => $t('admin.builder.image'),
        'noImageSelected' => $t('admin.builder.no_image_selected'),
        'contactForm' => $t('admin.builder.contact_form'),
        'faqItem' => $t('admin.builder.faq_item'),
        'faqAccordion' => $t('admin.builder.faq_accordion'),
        'productDetails' => $t('admin.builder.product_details'),
        'sectionGap' => $t('admin.builder.section_gap'),
        'columnsN' => $t('admin.builder.columns_n'),
        'galleryN' => $t('admin.builder.gallery_n'),
        'tableRows' => $t('admin.builder.table_rows'),
        'embedEmpty' => $t('admin.builder.embed_empty'),
        'canvasLabel' => $t('admin.builder.canvas_label'),
        'patternsSection' => $t('admin.builder.patterns_section'),
        'savePattern' => $t('admin.builder.save_pattern'),
        'patternName' => $t('admin.builder.pattern_name'),
        'patternNamePlaceholder' => $t('admin.builder.pattern_name_placeholder'),
        'props' => [
            'text' => $t('admin.builder.prop.text'),
            'level' => $t('admin.builder.prop.level'),
            'label' => $t('admin.builder.prop.label'),
            'href' => $t('admin.builder.prop.href'),
            'style' => $t('admin.builder.prop.style'),
            'width' => $t('admin.builder.prop.width'),
            'padding' => $t('admin.builder.prop.padding'),
            'assetId' => $t('admin.builder.prop.assetId'),
            'assetIds' => $t('admin.builder.prop.assetIds'),
            'alt' => $t('admin.builder.prop.alt'),
            'src' => $t('admin.builder.prop.src'),
            'url' => $t('admin.builder.prop.url'),
            'csv' => $t('admin.builder.prop.csv'),
            'header' => $t('admin.builder.prop.header'),
            'caption' => $t('admin.builder.prop.caption'),
            'aspect' => $t('admin.builder.prop.aspect'),
            'title' => $t('admin.builder.prop.title'),
            'message' => $t('admin.builder.prop.message'),
            'columns' => $t('admin.builder.prop.columns'),
            'heading' => $t('admin.builder.prop.heading'),
            'submitLabel' => $t('admin.builder.prop.submitLabel'),
            'successMessage' => $t('admin.builder.prop.successMessage'),
            'name' => $t('admin.builder.prop.name'),
            'sku' => $t('admin.builder.prop.sku'),
            'brand' => $t('admin.builder.prop.brand'),
            'price' => $t('admin.builder.prop.price'),
            'currency' => $t('admin.builder.prop.currency'),
            'availability' => $t('admin.builder.prop.availability'),
            'image' => $t('admin.builder.prop.image'),
            'question' => $t('admin.builder.prop.question'),
            'answer' => $t('admin.builder.prop.answer'),
        ],
        'enums' => [
            'narrow' => $t('admin.builder.enum.narrow'),
            'wide' => $t('admin.builder.enum.wide'),
            'full' => $t('admin.builder.enum.full'),
            'sm' => $t('admin.builder.enum.sm'),
            'md' => $t('admin.builder.enum.md'),
            'lg' => $t('admin.builder.enum.lg'),
            'InStock' => $t('admin.builder.enum.in_stock'),
            'OutOfStock' => $t('admin.builder.enum.out_of_stock'),
            'PreOrder' => $t('admin.builder.enum.preorder'),
            'Discontinued' => $t('admin.builder.enum.discontinued'),
            'LimitedAvailability' => $t('admin.builder.enum.limited'),
            'xl' => $t('admin.builder.enum.xl'),
            'primary' => $t('admin.builder.enum.primary'),
            'secondary' => $t('admin.builder.enum.secondary'),
            'yes' => $t('admin.builder.enum.yes'),
            'no' => $t('admin.builder.enum.no'),
            '16:9' => $t('admin.builder.enum.16_9'),
            '4:3' => $t('admin.builder.enum.4_3'),
            '1:1' => $t('admin.builder.enum.1_1'),
            '2' => '2',
            '3' => '3',
            '4' => '4',
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
<script src="<?php echo $e($basePath) ?>/assets/admin/builder.js"></script>
<script>
(function () {
  var title = document.getElementById('page-title');
  var heading = document.querySelector('.nx-builder-heading');
  if (title && heading) {
    title.addEventListener('input', function () {
      var value = title.value.trim();
      heading.textContent = value !== '' ? value : '…';
    });
  }

  var panel = document.getElementById('nx-seo-panel');
  if (!panel) return;
  var noDesc = <?php echo json_encode($t('admin.builder.seo_preview_no_desc'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var weights = {
    keyword_set: 15,
    keyword_in_title: 20,
    keyword_in_description: 15,
    title_length: 20,
    description_length: 20,
    robots_index: 10
  };

  function val(id) {
    var el = document.getElementById(id);
    return el ? String(el.value || '') : '';
  }

  function refresh() {
    var pageTitle = val('page-title').trim() || panel.getAttribute('data-page-title') || '';
    var metaTitle = val('meta_title').trim();
    var metaDesc = val('meta_description').trim();
    var keyword = val('focus_keyword').trim().toLowerCase();
    var robots = val('robots');
    var seoTitle = metaTitle !== '' ? metaTitle : pageTitle;
    var titleLen = seoTitle.length;
    var descLen = metaDesc.length;
    var checks = {
      keyword_set: keyword !== '',
      keyword_in_title: keyword !== '' && seoTitle.toLowerCase().indexOf(keyword) !== -1,
      keyword_in_description: keyword !== '' && metaDesc.toLowerCase().indexOf(keyword) !== -1,
      title_length: titleLen >= 30 && titleLen <= 60,
      description_length: descLen >= 70 && descLen <= 160,
      robots_index: robots.indexOf('noindex') === -1
    };
    var earned = 0;
    var total = 0;
    Object.keys(weights).forEach(function (id) {
      total += weights[id];
      if (checks[id]) earned += weights[id];
      var li = panel.querySelector('[data-check="' + id + '"]');
      if (!li) return;
      li.classList.toggle('is-ok', !!checks[id]);
      li.classList.toggle('is-bad', !checks[id]);
      var mark = li.querySelector('.nx-seo-check-mark');
      if (mark) mark.textContent = checks[id] ? '✓' : '·';
    });
    var scoreEl = document.getElementById('nx-seo-score-value');
    var score = Math.round((earned / total) * 100);
    if (scoreEl) scoreEl.textContent = String(score);
    var summaryScore = document.getElementById('nx-seo-summary-score');
    if (summaryScore) {
      summaryScore.textContent = String(score) + '/100';
      summaryScore.setAttribute('data-score', String(score));
      summaryScore.classList.toggle('is-low', score < 40);
      summaryScore.classList.toggle('is-mid', score >= 40 && score < 70);
    }

    var previewTitle = seoTitle || '…';
    var previewDesc = metaDesc !== '' ? metaDesc : noDesc;
    var map = {
      'nx-seo-serp-title': previewTitle,
      'nx-seo-serp-desc': previewDesc,
      'nx-seo-og-title': previewTitle,
      'nx-seo-og-desc': previewDesc
    };
    Object.keys(map).forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.textContent = map[id];
    });
  }

  ['page-title', 'meta_title', 'meta_description', 'focus_keyword', 'robots'].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', refresh);
    el.addEventListener('change', refresh);
  });
  refresh();
})();
</script>
