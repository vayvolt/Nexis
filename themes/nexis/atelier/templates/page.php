<!DOCTYPE html>
<html lang="<?php echo $e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e($page->documentTitle) ?> – <?php echo $e($site->name) ?></title>
    <?php if ($page->metaDescription): ?>
        <meta name="description" content="<?php echo $e($page->metaDescription) ?>">
    <?php endif; ?>
    <meta name="robots" content="<?php echo $e(!empty($isPreview) ? 'noindex,nofollow' : $page->robots) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?php echo $e($site->name) ?>">
    <meta property="og:title" content="<?php echo $e($page->documentTitle) ?>">
    <?php if ($page->metaDescription): ?>
        <meta property="og:description" content="<?php echo $e($page->metaDescription) ?>">
    <?php endif; ?>
    <meta property="og:url" content="<?php echo $e($canonical) ?>">
    <meta property="og:locale" content="<?php echo $e($ogLocale ?? $locale) ?>">
    <?php foreach ($alternates as $alt): ?>
        <?php if (($alt['published'] ?? true) === true && ($alt['locale'] ?? '') !== $locale): ?>
            <meta property="og:locale:alternate" content="<?php echo $e($alt['ogLocale'] ?? $alt['hreflang'] ?? $alt['locale']) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($logoUrl !== ''): ?>
        <meta property="og:image" content="<?php echo $e($logoUrl) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?php echo $e($canonical) ?>">
    <?php foreach ($alternates as $alt): ?>
        <?php if (($alt['published'] ?? true) === true): ?>
            <link rel="alternate" hreflang="<?php echo $e($alt['hreflang']) ?>" href="<?php echo $e($alt['url']) ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <style><?php echo $cssVariables ?></style>
    <?php if ($themeCssUrl !== ''): ?>
        <link rel="stylesheet" href="<?php echo $e($themeCssUrl) ?>">
    <?php endif; ?>
    <?php echo is_callable($slot ?? null) ? $slot('theme.head') : '' ?>
</head>
<?php
$navPosition = $layout['navPosition'] ?? 'right';
$footerPosition = $layout['footerPosition'] ?? 'split';
$logoInNav = !empty($layout['logoInNav']);
$showSiteName = ($layout['showSiteName'] ?? true) !== false;
$pageTitleMode = $layout['pageTitle'] ?? 'hide';
$bodyClass = 'theme-shell theme-atelier layout-nav-' . $e($navPosition)
    . ' layout-footer-' . $e($footerPosition)
    . ($logoInNav ? ' layout-logo-nav' : '');
?>
<body class="<?php echo $bodyClass ?>">
<header class="theme-header">
    <a class="theme-brand" href="<?php echo $e($homeUrl) ?>">
        <?php if ($logoUrl !== '' && !$logoInNav): ?>
            <img src="<?php echo $e($logoUrl) ?>" alt="">
        <?php endif; ?>
        <?php if ($showSiteName): ?>
            <span><?php echo $e($site->name) ?></span>
        <?php else: ?>
            <span class="visually-hidden"><?php echo $e($site->name) ?></span>
        <?php endif; ?>
    </a>
    <div class="theme-header-navs">
        <nav class="theme-nav" aria-label="<?php echo $e((string) ($themeUi['ariaPrimary'] ?? 'Main navigation')) ?>">
            <?php if ($logoInNav && $logoUrl !== ''): ?>
                <a class="theme-nav-logo" href="<?php echo $e($homeUrl) ?>"><img src="<?php echo $e($logoUrl) ?>" alt="<?php echo $e($site->name) ?>"></a>
            <?php endif; ?>
            <?php foreach (($primaryNav ?? []) as $item): ?>
                <?php if (!empty($item['children'])): ?>
                    <span class="theme-nav-item has-children">
                        <a href="<?php echo $e($item['url']) ?>"><?php echo $e($item['label']) ?></a>
                        <span class="theme-nav-sub">
                            <?php foreach ($item['children'] as $child): ?>
                                <a href="<?php echo $e($child['url']) ?>"><?php echo $e($child['label']) ?></a>
                            <?php endforeach; ?>
                        </span>
                    </span>
                <?php else: ?>
                    <a href="<?php echo $e($item['url']) ?>"><?php echo $e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <nav class="theme-nav theme-nav--locales" aria-label="<?php echo $e((string) ($themeUi['ariaLocales'] ?? 'Languages')) ?>">
            <?php foreach ($alternates as $alt): ?>
                <?php if (($alt['published'] ?? true) === true): ?>
                    <a href="<?php echo $e($alt['url']) ?>"<?php echo ($alt['locale'] ?? '') === $locale ? ' aria-current="page" class="is-active"' : '' ?>><?php echo $e(strtoupper((string) $alt['locale'])) ?></a>
                <?php else: ?>
                    <span class="is-unavailable" title="<?php echo $e((string) ($alt['unavailableLabel'] ?? ($themeUi['unavailable'] ?? 'coming soon'))) ?>"><?php echo $e(strtoupper((string) $alt['locale'])) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php echo is_callable($slot ?? null) ? $slot('theme.header-actions') : '' ?>
</header>
<main class="theme-main">
    <article>
        <?php if ($pageTitleMode === 'show'): ?>
            <h1 class="theme-page-title"><?php echo $e($page->title) ?></h1>
        <?php endif; ?>
        <?php echo $contentHtml ?>
    </article>
</main>
<footer class="theme-footer">
    <nav class="theme-footer-nav" aria-label="<?php echo $e((string) ($themeUi['ariaFooter'] ?? 'Footer')) ?>">
        <?php foreach (($footerNav ?? []) as $item): ?>
            <a href="<?php echo $e($item['url']) ?>"><?php echo $e($item['label']) ?></a>
            <?php foreach (($item['children'] ?? []) as $child): ?>
                <a href="<?php echo $e($child['url']) ?>"><?php echo $e($child['label']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php echo is_callable($slot ?? null) ? $slot('theme.footer-links') : '' ?>
    </nav>
    <?php
    $showFooterSite = (bool) ($layout['footerShowSiteName'] ?? true);
    $showFooterTheme = (bool) ($layout['footerShowThemeName'] ?? true);
    if ($showFooterSite || $showFooterTheme):
        $footerMeta = [];
        if ($showFooterSite) {
            $footerMeta[] = $site->name;
        }
        if ($showFooterTheme) {
            $footerMeta[] = (string) $themeName;
        }
        ?>
        <p class="theme-footer-meta"><?php echo $e(implode(' · ', $footerMeta)) ?></p>
    <?php endif; ?>
</footer>
<?php echo is_callable($slot ?? null) ? $slot('theme.footer') : '' ?>
</body>
</html>
