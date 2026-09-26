<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$uiLocale = $uiLocale ?? 'de';
$siteName = $site->name ?? 'Nexis';
$pageTitle = $title ?? $t('account.title_suffix', ['site' => $siteName], $siteName . ' – Konto');
?>
<!DOCTYPE html>
<html lang="<?php echo $e($uiLocale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e($pageTitle) ?></title>
    <style>
        :root { color-scheme: light; --bg:#f4f1ea; --ink:#1c1917; --muted:#57534e; --line:#d6d3d1; --accent:#1b4d3e; --card:#fff; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.5 "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); min-height: 100vh; }
        a { color: var(--accent); }
        .wrap { max-width: 28rem; margin: 0 auto; padding: 2rem 1rem 3rem; }
        .brand { font-weight: 700; margin: 0 0 1.25rem; }
        .brand a { color: inherit; text-decoration: none; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem; }
        h1 { font-size: 1.35rem; margin: 0 0 1rem; }
        label { display: block; font-weight: 600; margin: .75rem 0 .3rem; }
        input { width: 100%; padding: .5rem .6rem; border: 1px solid var(--line); border-radius: 6px; font: inherit; }
        button, .btn { display: inline-block; background: var(--accent); color: #fff; border: 0; border-radius: 6px; padding: .55rem .9rem; text-decoration: none; cursor: pointer; font: inherit; }
        .muted { color: var(--muted); }
        .error { color: #9f1239; margin: 0 0 1rem; }
        .flash { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: .75rem 1rem; border-radius: 8px; margin: 0 0 1rem; font-weight: 600; }
        .links { margin-top: 1rem; font-size: .95rem; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .fallback-nav { display: flex; flex-wrap: wrap; gap: .75rem; margin: 0 0 1.25rem; padding: 0 0 1rem; border-bottom: 1px solid var(--line); }
        .fallback-nav a { text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <p class="brand"><a href="<?php echo $e($basePath ?? '') ?>/"><?php echo $e($siteName) ?></a></p>
    <nav class="fallback-nav" aria-label="<?php echo $e($t('account.nav.aria')) ?>">
        <a href="<?php echo $e($basePath ?? '') ?>/"><?php echo $e($t('account.nav.website')) ?></a>
        <a href="<?php echo $e($basePath ?? '') ?>/account/login"><?php echo $e($t('account.nav.login')) ?></a>
        <a href="<?php echo $e($basePath ?? '') ?>/account/register"><?php echo $e($t('account.nav.register')) ?></a>
    </nav>
    <?php echo $content ?>
</div>
</body>
</html>
