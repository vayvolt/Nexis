<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$uiLocale = $uiLocale ?? $locale ?? 'de';
$title = $t('public.search.title');
if ($query !== '') {
    $title .= ' – ' . $query;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $e($uiLocale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e($title) ?></title>
    <?php if ($themeCssUrl !== ''): ?><link rel="stylesheet" href="<?php echo $e($themeCssUrl) ?>"><?php endif; ?>
    <style><?php echo $cssVariables ?></style>
</head>
<body>
<header>
    <a href="<?php echo $e($homeUrl) ?>"><?php echo $e($site->name) ?></a>
</header>
<main>
    <h1><?php echo $e($t('public.search.title')) ?></h1>
    <form method="get" action="">
        <input type="search" name="q" value="<?php echo $e($query) ?>" placeholder="<?php echo $e($t('public.search.placeholder')) ?>">
        <button type="submit"><?php echo $e($t('public.search.submit')) ?></button>
    </form>
    <?php if ($query !== '' && $hits === []): ?>
        <p><?php echo $e($t('public.search.no_results')) ?></p>
    <?php endif; ?>
    <ul>
        <?php foreach ($hits as $hit): ?>
            <?php
            $prefix = '';
            foreach ($site->locales as $loc) {
                if ($loc->locale === $hit->locale) {
                    $prefix = $loc->urlPrefix !== '' ? '/' . $loc->urlPrefix : '';
                    break;
                }
            }
            $href = $basePath . $prefix . ($hit->path === '/' ? '' : $hit->path);
            if ($hit->path === '/') {
                $href = $basePath . ($prefix !== '' ? $prefix : '/');
            }
            ?>
            <li>
                <a href="<?php echo $e($href) ?>"><?php echo $e($hit->title) ?></a>
                <?php if ($hit->excerpt !== ''): ?><p><?php echo $e($hit->excerpt) ?></p><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</main>
</body>
</html>
