<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$uiLocale = (string) ($uiLocale ?? 'de');
$statusLabel = (string) ($statusLabel ?? (string) ($status ?? 500));
$errorTitle = (string) ($errorTitle ?? $t('http.internal_error'));
$errorHeading = (string) ($errorHeading ?? $errorTitle);
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
    <title><?php echo $e($statusLabel . ' · ' . $errorTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $e(\Nexis\Kernel\Nexis::brandUrl($basePath, \Nexis\Kernel\Nexis::BRAND_ICON)) ?>">
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font: 16px/1.55 "Segoe UI", system-ui, sans-serif;
            background:
                radial-gradient(1200px 600px at 10% -10%, #dcefe8 0%, transparent 55%),
                radial-gradient(900px 500px at 100% 0%, #f0e7d8 0%, transparent 50%),
                #f4f1ea;
            color: #1c1917;
        }
        .shell { width: min(34rem, 92vw); padding: 1.25rem; }
        .status {
            display: inline-flex; align-items: center; gap: .35rem;
            font: 700 .78rem/1 system-ui, sans-serif; letter-spacing: .06em; text-transform: uppercase;
            color: #1b4d3e; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 999px;
            padding: .4rem .7rem; margin: 0 0 .85rem;
        }
        h1 { margin: 0 0 .45rem; font-size: clamp(1.6rem, 4vw, 2rem); letter-spacing: -.02em; }
        p { margin: .4rem 0; color: #44403c; }
        .muted { color: #78716c; font-size: .9rem; }
        .actions { margin-top: 1.25rem; display: flex; gap: .5rem; flex-wrap: wrap; }
        a.btn {
            display: inline-block; background: #1b4d3e; color: #fff; text-decoration: none;
            border-radius: 8px; padding: .6rem 1rem; font-weight: 650;
        }
        a.btn:hover { filter: brightness(1.05); }
        code { font-size: .85em; }
    </style>
</head>
<body>
<main class="shell">
    <span class="status"><?php echo $e($statusLabel) ?></span>
    <h1><?php echo $e($errorHeading) ?></h1>
    <p><?php echo $e($message) ?></p>
    <?php if ($requestId !== ''): ?>
        <p class="muted">Request-ID: <code><?php echo $e($requestId) ?></code></p>
    <?php endif; ?>
    <div class="actions">
        <a class="btn" href="<?php echo $e($homeUrl) ?>"><?php echo $e($homeLabel) ?></a>
    </div>
</main>
</body>
</html>
