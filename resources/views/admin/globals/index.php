<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var array<string, string> $headerCtaLabel */
/** @var array<string, string> $headerCtaHref */
/** @var array<string, string> $footerTeaser */
$locales = $site->enabledLocales();
$canManage = !empty($canManage);
?>
<h1><?php echo $e($t('admin.globals.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.globals.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.common.saved')) ?></p><?php endif; ?>
<?php if (!$canManage): ?>
    <p class="muted"><?php echo $e($t('admin.settings.readonly')) ?></p>
<?php endif; ?>

<form method="post" action="<?php echo $e($basePath) ?>/admin/globals" <?php echo !$canManage ? 'onsubmit="return false"' : '' ?>>
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">

    <div class="card locale-tabs" data-locale-tabs>
        <h2><?php echo $e($t('admin.globals.header_cta')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.globals.header_cta_hint')) ?></p>
        <div class="locale-tabs__list" role="tablist" aria-label="<?php echo $e($t('admin.settings.locales')) ?>">
            <?php foreach ($locales as $i => $loc): ?>
                <button type="button"
                        class="locale-tabs__tab"
                        role="tab"
                        id="globals-cta-tab-<?php echo $e($loc->locale) ?>"
                        data-locale-tab="<?php echo $e($loc->locale) ?>"
                        aria-controls="globals-cta-panel-<?php echo $e($loc->locale) ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false' ?>"
                        tabindex="<?php echo $i === 0 ? '0' : '-1' ?>">
                    <?php echo $e(strtoupper($loc->locale)) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($locales as $i => $loc): ?>
            <div class="locale-tabs__panel"
                 role="tabpanel"
                 id="globals-cta-panel-<?php echo $e($loc->locale) ?>"
                 data-locale-panel="<?php echo $e($loc->locale) ?>"
                 aria-labelledby="globals-cta-tab-<?php echo $e($loc->locale) ?>"
                 <?php echo $i === 0 ? '' : 'hidden' ?>>
                <label for="cta-label-<?php echo $e($loc->locale) ?>"><?php echo $e($t('admin.globals.cta_label')) ?></label>
                <input id="cta-label-<?php echo $e($loc->locale) ?>"
                       name="header_cta_label[<?php echo $e($loc->locale) ?>]"
                       type="text"
                       maxlength="190"
                       value="<?php echo $e($headerCtaLabel[$loc->locale] ?? '') ?>"
                       <?php echo !$canManage ? 'disabled' : '' ?>>
                <label for="cta-href-<?php echo $e($loc->locale) ?>"><?php echo $e($t('admin.globals.cta_href')) ?></label>
                <input id="cta-href-<?php echo $e($loc->locale) ?>"
                       name="header_cta_href[<?php echo $e($loc->locale) ?>]"
                       type="text"
                       maxlength="500"
                       value="<?php echo $e($headerCtaHref[$loc->locale] ?? '') ?>"
                       placeholder="/de/kontakt"
                       <?php echo !$canManage ? 'disabled' : '' ?>>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card locale-tabs" data-locale-tabs style="margin-top:1rem">
        <h2><?php echo $e($t('admin.globals.footer_teaser')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.globals.footer_teaser_hint')) ?></p>
        <div class="locale-tabs__list" role="tablist" aria-label="<?php echo $e($t('admin.settings.locales')) ?>">
            <?php foreach ($locales as $i => $loc): ?>
                <button type="button"
                        class="locale-tabs__tab"
                        role="tab"
                        id="globals-teaser-tab-<?php echo $e($loc->locale) ?>"
                        data-locale-tab="<?php echo $e($loc->locale) ?>"
                        aria-controls="globals-teaser-panel-<?php echo $e($loc->locale) ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false' ?>"
                        tabindex="<?php echo $i === 0 ? '0' : '-1' ?>">
                    <?php echo $e(strtoupper($loc->locale)) ?>
                </button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($locales as $i => $loc): ?>
            <div class="locale-tabs__panel"
                 role="tabpanel"
                 id="globals-teaser-panel-<?php echo $e($loc->locale) ?>"
                 data-locale-panel="<?php echo $e($loc->locale) ?>"
                 aria-labelledby="globals-teaser-tab-<?php echo $e($loc->locale) ?>"
                 <?php echo $i === 0 ? '' : 'hidden' ?>>
                <label for="teaser-<?php echo $e($loc->locale) ?>"><?php echo $e($t('admin.globals.teaser_text')) ?></label>
                <textarea id="teaser-<?php echo $e($loc->locale) ?>"
                          name="footer_teaser[<?php echo $e($loc->locale) ?>]"
                          rows="3"
                          maxlength="500"
                          <?php echo !$canManage ? 'disabled' : '' ?>><?php echo $e($footerTeaser[$loc->locale] ?? '') ?></textarea>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($canManage): ?>
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('admin.common.save')) ?></button></p>
    <?php endif; ?>
</form>
