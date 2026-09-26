<!DOCTYPE html>
<html lang="<?php echo $e($uiLocale ?? 'de') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $e(($t ?? static fn (string $k): string => $k)('admin.login.2fa_title')) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?php echo $e(\Nexis\Kernel\Nexis::brandUrl((string) ($basePath ?? ''), \Nexis\Kernel\Nexis::BRAND_ICON)) ?>">
    <style>
        body { font: 16px/1.5 "Segoe UI", system-ui, sans-serif; background: #f4f1ea; color: #1c1917; display: grid; place-items: center; min-height: 100vh; margin: 0; }
        .login-wrap { width: min(24rem, 92vw); position: relative; }
        .lang-switch { position: absolute; top: 0; right: 0; display: inline-flex; gap: .15rem; padding: .15rem; border: 1px solid #d6d3d1; border-radius: 7px; background: #fafaf9; }
        .lang-switch a {
            display: inline-flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; letter-spacing: .02em; text-decoration: none;
            color: #78716c; padding: .3rem .55rem; border-radius: 5px; border: 1px solid transparent;
            background: transparent;
        }
        .lang-switch a:hover { color: #1b4d3e; background: #fff; text-decoration: none; }
        .lang-switch a[aria-current="true"] {
            color: #1b4d3e; background: #fff; border-color: #d6d3d1;
            box-shadow: 0 0 0 1px #d6d3d1;
        }
        .login-brand { display: flex; flex-direction: column; align-items: center; gap: .35rem; margin: 0 0 1rem; text-align: center; padding-top: 1.75rem; }
        .nexis-brand { display: inline-flex; align-items: center; justify-content: center; }
        .nexis-brand__img--logo { height: 2.75rem; width: auto; display: block; }
        .vendor { margin: 0; font-size: .8rem; color: #57534e; }
        .vendor a { color: inherit; }
        form { background: #fff; padding: 1.5rem; border: 1px solid #d6d3d1; border-radius: 8px; }
        .login-heading { margin: 0 0 .25rem; font-size: 1.15rem; font-weight: 650; }
        .login-hint { margin: 0 0 .5rem; font-size: .9rem; color: #57534e; }
        label { display: block; font-weight: 600; margin: .75rem 0 .3rem; }
        input { width: 100%; padding: .5rem .6rem; border: 1px solid #d6d3d1; border-radius: 6px; font: inherit; box-sizing: border-box; letter-spacing: .12em; }
        button { margin-top: 1rem; width: 100%; background: #1b4d3e; color: #fff; border: 0; border-radius: 6px; padding: .6rem; font: inherit; cursor: pointer; }
        .error { color: #9f1239; margin: 0 0 .5rem; }
        .login-foot { margin: .85rem 0 0; text-align: center; font-size: .8rem; color: #78716c; }
        .login-foot a { color: inherit; }
    </style>
</head>
<body>
<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$currentLang = (string) ($uiLocale ?? 'de');
$bp = (string) ($basePath ?? '');
?>
<div class="login-wrap">
    <nav class="lang-switch" aria-label="<?php echo $e($t('admin.login.lang_nav')) ?>">
        <a href="<?php echo $e($bp) ?>/admin/login/2fa?lang=de"<?php echo $currentLang === 'de' ? ' aria-current="true"' : '' ?>>Deutsch</a>
        <a href="<?php echo $e($bp) ?>/admin/login/2fa?lang=en"<?php echo $currentLang === 'en' ? ' aria-current="true"' : '' ?>>English</a>
    </nav>
    <div class="login-brand">
        <?php
        $variant = 'logo';
        $href = null;
        require dirname(__DIR__) . '/partials/nexis-brand.php';
        ?>
        <p class="vendor">
            <a href="<?php echo $e(\Nexis\Kernel\Nexis::VENDOR_URL) ?>" target="_blank" rel="noopener"><?php echo $e($t('admin.login.attribution')) ?></a>
        </p>
    </div>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/login/2fa">
        <h1 class="login-heading"><?php echo $e($t('admin.login.2fa_heading')) ?></h1>
        <p class="login-hint"><?php echo $e($t('admin.login.2fa_hint')) ?></p>
        <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label for="code"><?php echo $e($t('admin.login.2fa_code')) ?></label>
        <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" required autofocus>
        <button type="submit"><?php echo $e($t('admin.login.2fa_submit')) ?></button>
    </form>
    <p class="login-foot">
        <a href="<?php echo $e($basePath) ?>/admin/login"><?php echo $e($t('admin.login.2fa_back')) ?></a>
        · <?php echo $e($t('admin.login.tagline')) ?>
    </p>
</div>
</body>
</html>
