<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$contentLocale = (string) ($locale ?? $site->defaultLocale);
?>
<h1><?php echo $e($site->name) ?></h1>
<p class="muted">
    <?php echo $e($site->primaryDomain) ?>
    · <?php echo $e($t('admin.dashboard.languages', ['count' => (string) (int) $localeCount])) ?>
    · <?php echo $e($t('admin.dashboard.strategy')) ?> <?php echo $e($site->localeUrlStrategy->value) ?>
    · <a href="<?php echo $e($homeUrl) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.dashboard.open_site')) ?></a>
</p>

<div class="dash-grid">
    <section class="card dash-card">
        <h2><?php echo $e($t('admin.nav.content')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.dashboard.content_stats', [
            'pages' => (string) (int) $pageCount,
            'media' => (string) (int) $mediaCount,
        ])) ?></p>
        <p class="dash-links">
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/pages?locale=<?php echo $e($contentLocale) ?>"><?php echo $e($t('admin.nav.pages')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/menus?locale=<?php echo $e($contentLocale) ?>"><?php echo $e($t('admin.nav.menus')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/media"><?php echo $e($t('admin.nav.media')) ?></a>
            <?php foreach (($dashboardContentSlots ?? []) as $slotHtml): ?>
                <?php echo $slotHtml ?>
            <?php endforeach; ?>
        </p>
    </section>
    <section class="card dash-card">
        <h2><?php echo $e($t('admin.nav.site')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.dashboard.site_blurb')) ?></p>
        <p class="dash-links">
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/theme"><?php echo $e($t('admin.nav.theme')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/settings"><?php echo $e($t('admin.nav.settings')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/settings#locales"><?php echo $e($t('admin.dashboard.languages_link')) ?></a>
            <?php foreach (($dashboardSiteSlots ?? []) as $slotHtml): ?>
                <?php echo $slotHtml ?>
            <?php endforeach; ?>
        </p>
    </section>
    <section class="card dash-card">
        <h2><?php echo $e($t('admin.nav.system')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.dashboard.system_blurb')) ?></p>
        <p class="dash-links">
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/users"><?php echo $e($t('admin.nav.users')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/plugins"><?php echo $e($t('admin.nav.plugins')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/audit"><?php echo $e($t('admin.nav.audit')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/mail"><?php echo $e($t('admin.nav.mail')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/logs"><?php echo $e($t('admin.nav.logs')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/health"><?php echo $e($t('admin.nav.health')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/webhooks"><?php echo $e($t('admin.nav.webhooks')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/export"><?php echo $e($t('admin.nav.export')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/security"><?php echo $e($t('admin.nav.security')) ?></a>
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/about"><?php echo $e($t('admin.nav.about')) ?></a>
        </p>
    </section>
</div>
