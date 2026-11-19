<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$uiLocale = (string) ($uiLocale ?? 'de');
$notFoundTitle = (string) ($notFoundTitle ?? $errorTitle ?? $t('public.not_found.title'));
$notFoundHeading = (string) ($notFoundHeading ?? $errorHeading ?? $t('public.not_found.page'));
$message = (string) ($message ?? '');
$requestId = (string) ($requestId ?? '');
$homeUrl = (string) ($homeUrl ?? '/');
$homeLabel = (string) ($homeLabel ?? $t('public.error.home'));
$basePath = (string) ($basePath ?? '');
?>
<!DOCTYPE html>
<html lang="<?php echo $e($uiLocale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e($notFoundTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $e(\Nexis\Kernel\Nexis::brandUrl($basePath, \Nexis\Kernel\Nexis::BRAND_ICON)) ?>">
    <style>
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font: 16px/1.55 "Segoe UI", system-ui, sans-serif;
            background:
                radial-gradient(1000px 520px at 0% 0%, #e7e5e4 0%, transparent 55%),
                #f4f1ea;
            color: #1c1917;
        }
        .shell { width: min(34rem, 92vw); padding: 1.25rem; }
        .status {
            display: inline-block; font: 700 .75rem/1 system-ui, sans-serif; letter-spacing: .06em;
            text-transform: uppercase; color: #78716c; background: #fafaf9; border: 1px solid #d6d3d1;
            border-radius: 999px; padding: .35rem .65rem; margin: 0 0 .85rem;
        }
        h1 { margin: 0 0 .45rem; font-size: clamp(1.6rem, 4vw, 2rem); letter-spacing: -.02em; }
        p { margin: .4rem 0; color: #44403c; }
        .muted { color: #78716c; font-size: .9rem; }
        a.btn {
            display: inline-block; margin-top: 1.1rem; background: #1b4d3e; color: #fff;
            text-decoration: none; border-radius: 8px; padding: .6rem 1rem; font-weight: 650;
        }
        code { font-size: .85em; }
    </style>
</head>
<body>
<main class="shell">
    <span class="status">404</span>
    <h1><?php echo $e($notFoundHeading) ?></h1>
    <p><?php echo $e($message) ?></p>
    <?php if ($requestId !== ''): ?>
        <p class="muted">Request-ID: <code><?php echo $e($requestId) ?></code></p>
    <?php endif; ?>
    <a class="btn" href="<?php echo $e($homeUrl) ?>"><?php echo $e($homeLabel) ?></a>
</main>
</body>
</html>
