<!DOCTYPE html>
<html lang="<?php echo $e($locale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e($page->title) ?> – <?php echo $e($site->name) ?></title>
    <link rel="canonical" href="<?php echo $e($canonical) ?>">
    <?php foreach ($alternates as $alt): ?>
        <link rel="alternate" hreflang="<?php echo $e($alt['hreflang']) ?>" href="<?php echo $e($alt['url']) ?>">
    <?php endforeach; ?>
    <style>
        body { margin: 0 auto; max-width: 48rem; padding: 2rem 1.25rem; font: 18px/1.6 "Georgia", serif; color: #1c1917; }
        nav { font: 14px/1.4 "Segoe UI", sans-serif; margin-bottom: 2rem; display: flex; gap: 1rem; }
        a { color: #1b4d3e; }
        .bk-section { margin: 0 0 1.5rem; }
        .bk-pad--sm { padding: .5rem 0; }
        .bk-pad--md { padding: 1rem 0; }
        .bk-pad--lg { padding: 1.5rem 0; }
        .bk-pad--xl { padding: 2.5rem 0; }
        .bk-columns { display: grid; gap: 1rem; }
        .bk-columns--2 { grid-template-columns: 1fr 1fr; }
        .bk-columns--3 { grid-template-columns: 1fr 1fr 1fr; }
        .bk-columns--4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .bk-btn { display: inline-block; padding: .55rem 1rem; background: #1b4d3e; color: #fff; text-decoration: none; border-radius: 4px; }
        .bk-btn--secondary { background: #57534e; }
        .bk-image img { max-width: 100%; height: auto; }
        .bk-fallback { padding: .75rem; border: 1px dashed #a8a29e; color: #57534e; font: 14px/1.4 "Segoe UI", sans-serif; }
        @media (max-width: 700px) {
            .bk-columns--2, .bk-columns--3, .bk-columns--4 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<nav>
    <strong><?php echo $e($site->name) ?></strong>
    <?php foreach ($alternates as $alt): ?>
        <a href="<?php echo $e($alt['url']) ?>"><?php echo $e($alt['locale']) ?></a>
    <?php endforeach; ?>
</nav>
<article>
    <?php echo $contentHtml ?>
</article>
</body>
</html>
