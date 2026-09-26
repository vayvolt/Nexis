<?php
/**
 * Locale chips with SEO score for page translation groups.
 *
 * @var \Nexis\Site\Site $site
 * @var array<string, \Nexis\Content\Page> $byLocale
 * @var string $basePath
 * @var callable $e
 * @var callable(string, array<string, scalar|null>=, ?string=): string $t
 */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$byLocale = $byLocale ?? [];
?>
<div class="nx-pages-locales">
    <?php foreach ($site->enabledLocales() as $loc): ?>
        <?php if (isset($byLocale[$loc->locale])): ?>
            <?php
            $alt = $byLocale[$loc->locale];
            $altScore = (int) (\Nexis\Content\PageSeoAnalysis::analyze($alt)['score']);
            $altScoreClass = $altScore < 40 ? ' is-low' : ($altScore < 70 ? ' is-mid' : '');
            ?>
            <a class="nx-pages-locale" href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($alt->id->value) ?>/builder" title="<?php echo $e($t('admin.pages.seo_locale_title', ['locale' => strtoupper($loc->locale), 'score' => (string) $altScore])) ?>">
                <span class="nx-pages-locale__code"><?php echo $e(strtoupper($loc->locale)) ?></span>
                <span class="nx-seo-summary-score<?php echo $e($altScoreClass) ?>"><?php echo $e((string) $altScore) ?></span>
            </a>
        <?php else: ?>
            <span class="nx-pages-locale nx-pages-locale--missing" title="<?php echo $e($t('admin.pages.locale_missing')) ?>">
                <span class="nx-pages-locale__code"><?php echo $e(strtoupper($loc->locale)) ?></span>
                <span class="muted">—</span>
            </span>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
