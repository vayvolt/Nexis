<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$uiLocale = $uiLocale ?? 'de';
?>
<!DOCTYPE html>
<html lang="<?php echo $e($uiLocale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e($t('public.not_found.title')) ?></title>
    <style><?php echo $cssVariables ?></style>
    <?php if ($themeCssUrl !== ''): ?>
        <link rel="stylesheet" href="<?php echo $e($themeCssUrl) ?>">
    <?php endif; ?>
</head>
<body class="theme-shell">
<main class="theme-main">
    <h1><?php echo $e($t('public.not_found.page')) ?></h1>
    <p><?php echo $e($message) ?></p>
    <?php if ($requestId !== ''): ?><p><small>Request-ID: <?php echo $e($requestId) ?></small></p><?php endif; ?>
</main>
</body>
</html>
