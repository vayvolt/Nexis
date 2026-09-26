<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var array<string, array<string, string>> $localeFields */
$locales = $site->enabledLocales();
$fieldMeta = [
    'text' => ['label' => $t('admin.consent.field.text'), 'type' => 'textarea'],
    'accept_label' => ['label' => $t('admin.consent.field.accept_label'), 'type' => 'text'],
    'reject_label' => ['label' => $t('admin.consent.field.reject_label'), 'type' => 'text'],
    'save_label' => ['label' => $t('admin.consent.field.save_label'), 'type' => 'text'],
    'privacy_label' => ['label' => $t('admin.consent.field.privacy_label'), 'type' => 'text'],
    'settings_label' => ['label' => $t('admin.consent.field.settings_label'), 'type' => 'text'],
    'analytics_label' => ['label' => $t('admin.consent.field.analytics_label'), 'type' => 'text'],
    'marketing_label' => ['label' => $t('admin.consent.field.marketing_label'), 'type' => 'text'],
];
?>
<h1><?php echo $e($t('admin.consent.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.consent.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.common.saved')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>
<?php if (!empty($enabled) && empty($trackingConfigured)): ?>
    <p class="flash flash--info" role="status"><?php echo $e($t('admin.consent.no_tracking_hint')) ?></p>
<?php endif; ?>

<form method="post" action="<?php echo $e($basePath) ?>/admin/consent">
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">

    <div class="card">
        <h2><?php echo $e($t('admin.consent.banner')) ?></h2>
        <label class="check-label" for="enabled">
            <input id="enabled" type="checkbox" name="enabled" value="1" <?php echo !empty($enabled) ? 'checked' : '' ?>>
            <span><?php echo $e($t('admin.consent.enabled')) ?></span>
        </label>
        <p class="muted" style="margin:.35rem 0 0"><?php echo $e($t('admin.consent.legal_hint')) ?></p>
        <label for="privacy_url"><?php echo $e($t('admin.consent.privacy_url')) ?></label>
        <input id="privacy_url" name="privacy_url" type="url" value="<?php echo $e($privacyUrl) ?>" placeholder="<?php echo $e($basePath) ?>/de/datenschutz">
        <p class="muted" style="margin-top:.35rem"><?php echo $e($t('admin.consent.privacy_hint')) ?></p>
    </div>

    <div class="card">
        <h2><?php echo $e($t('admin.consent.google')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.consent.google_hint')) ?></p>
        <div class="row">
            <div>
                <label for="ga_measurement_id">GA4 Measurement ID</label>
                <input id="ga_measurement_id" name="ga_measurement_id" value="<?php echo $e($gaId) ?>" placeholder="G-XXXXXXXXXX" autocomplete="off" spellcheck="false">
            </div>
            <div>
                <label for="gtm_id">Google Tag Manager</label>
                <input id="gtm_id" name="gtm_id" value="<?php echo $e($gtmId) ?>" placeholder="GTM-XXXXXXX" autocomplete="off" spellcheck="false">
            </div>
        </div>
        <label for="google_ads_id">Google Ads / Conversion ID</label>
        <input id="google_ads_id" name="google_ads_id" value="<?php echo $e($adsId) ?>" placeholder="AW-XXXXXXXXX" autocomplete="off" spellcheck="false">
        <p class="muted" style="margin-top:.5rem"><?php echo $e($t('admin.consent.flow_hint')) ?></p>
    </div>

    <div class="card locale-tabs" data-locale-tabs>
        <h2><?php echo $e($t('admin.consent.texts')) ?></h2>
        <?php if (count($locales) > 1): ?>
            <div class="locale-tabs__list" role="tablist" aria-label="<?php echo $e($t('admin.settings.locales')) ?>">
                <?php foreach ($locales as $i => $locale): ?>
                    <button
                        type="button"
                        class="locale-tabs__tab"
                        role="tab"
                        id="consent-tab-<?php echo $e($locale->locale) ?>"
                        data-locale-tab="<?php echo $e($locale->locale) ?>"
                        aria-controls="consent-panel-<?php echo $e($locale->locale) ?>"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false' ?>"
                        tabindex="<?php echo $i === 0 ? '0' : '-1' ?>"
                    ><?php echo $e($t('admin.locale.' . $locale->locale, [], $locale->label)) ?> <code><?php echo $e($locale->locale) ?></code></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ($locales as $i => $locale): ?>
            <?php $code = $locale->locale; ?>
            <div
                class="locale-tabs__panel"
                role="tabpanel"
                id="consent-panel-<?php echo $e($code) ?>"
                data-locale-panel="<?php echo $e($code) ?>"
                aria-labelledby="consent-tab-<?php echo $e($code) ?>"
                <?php echo $i === 0 ? '' : 'hidden' ?>
            >
                <?php if (count($locales) === 1): ?>
                    <h3><?php echo $e($t('admin.locale.' . $code, [], $locale->label)) ?> <code><?php echo $e($code) ?></code></h3>
                <?php endif; ?>
                <?php foreach ($fieldMeta as $field => $meta): ?>
                    <?php $value = $localeFields[$field][$code] ?? ''; ?>
                    <label for="<?php echo $e($field . '-' . $code) ?>"><?php echo $e($meta['label']) ?></label>
                    <?php if ($meta['type'] === 'textarea'): ?>
                        <textarea id="<?php echo $e($field . '-' . $code) ?>" name="<?php echo $e($field) ?>[<?php echo $e($code) ?>]" rows="3"><?php echo $e($value) ?></textarea>
                    <?php else: ?>
                        <input id="<?php echo $e($field . '-' . $code) ?>" name="<?php echo $e($field) ?>[<?php echo $e($code) ?>]" value="<?php echo $e($value) ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <p><button type="submit"><?php echo $e($t('admin.common.save')) ?></button></p>
</form>
