<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($page ? $t('admin.pages.settings') : $t('admin.pages.create')) ?></h1>
<?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
<?php if ($page): ?>
    <?php
    $localeSurface = 'edit';
    require __DIR__ . DIRECTORY_SEPARATOR . '_locale_switch.php';
    ?>
    <p class="muted"><a href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/builder"><?php echo $e($t('admin.common.builder')) ?></a></p>
<?php endif; ?>
<form method="post" action="<?php echo $e($basePath) ?><?php echo $page ? '/admin/pages/' . $e($page->id->value) : '/admin/pages' ?>">
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
    <?php if ($page): ?>
        <input type="hidden" name="locale" value="<?php echo $e($page->locale) ?>">
    <?php else: ?>
        <?php
        // Create form is unused (GET /new creates instantly); keep POST fallback fields.
        ?>
    <?php endif; ?>
    <div class="row">
        <div>
            <label for="title"><?php echo $e($t('admin.common.title')) ?></label>
            <input id="title" name="title" required value="<?php echo $e($page->title ?? '') ?>">
        </div>
        <div>
            <label for="slug"><?php echo $e($t('admin.common.slug')) ?></label>
            <input id="slug" name="slug" value="<?php echo $e($page->slug ?? '') ?>" placeholder="home">
        </div>
    </div>
    <div class="row">
        <?php if (!$page): ?>
            <div>
                <label for="locale"><?php echo $e($t('admin.pages.primary_language')) ?></label>
                <select id="locale" name="locale">
                    <?php foreach ($site->enabledLocales() as $loc): ?>
                        <option value="<?php echo $e($loc->locale) ?>" <?php echo ($locale === $loc->locale) ? 'selected' : '' ?>><?php echo $e($t('admin.locale.' . $loc->locale, [], $loc->label)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (count($site->enabledLocales()) > 1): ?>
                    <p class="muted"><?php echo $e($t('admin.pages.create_all_locales')) ?></p>
                <?php else: ?>
                    <p class="muted"><?php echo $e($t('admin.pages.create_nav')) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div>
            <label for="status"><?php echo $e($t('admin.common.status')) ?></label>
            <select id="status" name="status">
                <option value="draft" <?php echo ($page?->status->value ?? 'draft') === 'draft' ? 'selected' : '' ?>><?php echo $e($t('admin.status.draft_title')) ?></option>
                <?php if (($page?->status->value ?? '') === 'in_review'): ?>
                    <option value="in_review" selected><?php echo $e($t('admin.status.in_review_title')) ?></option>
                <?php endif; ?>
                <option value="scheduled" <?php echo ($page?->status->value ?? '') === 'scheduled' ? 'selected' : '' ?>><?php echo $e($t('admin.status.scheduled_title')) ?></option>
                <option value="published" <?php echo ($page?->status->value ?? '') === 'published' ? 'selected' : '' ?>><?php echo $e($t('admin.status.published_title')) ?></option>
            </select>
        </div>
    </div>
    <div class="row">
        <div>
            <label for="scheduled_at"><?php echo $e($t('admin.pages.schedule_label')) ?></label>
            <input id="scheduled_at" type="datetime-local" name="scheduled_at"
                   value="<?php echo $e(($page?->scheduledAt?->format('Y-m-d\TH:i')) ?? '') ?>">
            <p class="muted"><?php echo $e($t('admin.pages.schedule_hint')) ?></p>
        </div>
    </div>
    <label for="body_text"><?php echo $e($t('admin.pages.body_label')) ?></label>
    <textarea id="body_text" name="body_text" rows="8"><?php echo $e($page->bodyText ?? '') ?></textarea>
    <p class="muted"><?php echo $e($t('admin.pages.body_hint')) ?></p>
    <div class="row">
        <div>
            <label for="meta_title"><?php echo $e($t('admin.pages.seo_title')) ?></label>
            <input id="meta_title" name="meta_title" maxlength="190" value="<?php echo $e($page->metaTitle ?? '') ?>">
        </div>
        <div>
            <label for="meta_description"><?php echo $e($t('admin.pages.seo_desc')) ?></label>
            <input id="meta_description" name="meta_description" maxlength="320" value="<?php echo $e($page->metaDescription ?? '') ?>">
        </div>
    </div>
    <label for="robots"><?php echo $e($t('admin.pages.robots')) ?></label>
    <select id="robots" name="robots">
        <?php
        $robots = $page->robots ?? 'index,follow';
        foreach (['index,follow' => 'index, follow', 'noindex,follow' => 'noindex, follow', 'index,nofollow' => 'index, nofollow', 'noindex,nofollow' => 'noindex, nofollow'] as $value => $label):
            ?>
            <option value="<?php echo $e($value) ?>" <?php echo $robots === $value ? 'selected' : '' ?>><?php echo $e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <p><button type="submit"><?php echo $e($t('admin.common.save')) ?></button>
        <?php if ($page): ?>
            <a class="btn" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/builder"><?php echo $e($t('admin.common.builder')) ?></a>
        <?php endif; ?>
    </p>
</form>
