<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$uiLocale = (string) ($uiLocale ?? 'de');
$errorTitle = (string) ($errorTitle ?? $t('public.maintenance.title'));
$errorHeading = (string) ($errorHeading ?? $t('public.maintenance.heading'));
$message = (string) ($message ?? $t('public.maintenance.message'));
$requestId = (string) ($requestId ?? '');
$basePath = (string) ($basePath ?? '');
?>
<!DOCTYPE html>
<html lang="<?php echo $e($uiLocale) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo $e($errorTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $e(\Nexis\Kernel\Nexis::brandUrl($basePath, \Nexis\Kernel\Nexis::BRAND_ICON)) ?>">
    <style>
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font: 16px/1.55 "Segoe UI", system-ui, sans-serif;
            background:
                linear-gradient(160deg, #1b4d3e 0%, #245c4a 42%, #3f3a32 100%);
            color: #fafaf9;
        }
        .shell { width: min(34rem, 92vw); padding: 1.5rem; }
        .eyebrow {
            display: inline-block; font: 700 .75rem/1 system-ui, sans-serif; letter-spacing: .08em;
            text-transform: uppercase; opacity: .85; margin: 0 0 .75rem;
        }
        h1 { margin: 0 0 .5rem; font-size: clamp(1.7rem, 4vw, 2.2rem); letter-spacing: -.02em; }
        p { margin: .45rem 0; color: rgba(250, 250, 249, .88); }
        .muted { color: rgba(250, 250, 249, .65); font-size: .9rem; }
        code { font-size: .85em; }
    </style>
</head>
<body>
<main class="shell">
    <span class="eyebrow">503 · <?php echo $e($t('public.maintenance.badge')) ?></span>
    <h1><?php echo $e($errorHeading) ?></h1>
    <p><?php echo $e($message) ?></p>
    <?php if ($requestId !== ''): ?>
        <p class="muted">Request-ID: <code><?php echo $e($requestId) ?></code></p>
    <?php endif; ?>
</main>
</body>
</html>
