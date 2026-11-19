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
        button, .btn { margin-top: 1rem; width: 100%; background: #1b4d3e; color: #fff; border: 0; border-radius: 6px; padding: .6rem; font: inherit; cursor: pointer; display: inline-block; text-align: center; box-sizing: border-box; text-decoration: none; }
        .btn-muted { background: #78716c; }
        .sso-divider { display: flex; align-items: center; gap: .75rem; margin: 1rem 0 .25rem; color: #78716c; font-size: .8rem; }
        .sso-divider::before, .sso-divider::after { content: ""; flex: 1; border-top: 1px solid #d6d3d1; }
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
    <div style="background:#fff;padding:1.5rem;border:1px solid #d6d3d1;border-radius:8px">
        <h1 class="login-heading"><?php echo $e($t('admin.login.2fa_heading')) ?></h1>
        <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
        <?php if (!empty($passkeyEnabled)): ?>
            <p class="login-hint"><?php echo $e($t('admin.login.passkey_hint')) ?></p>
            <button
                type="button"
                class="btn"
                data-passkey-assert
                data-base-path="<?php echo $e($basePath) ?>"
                data-error="<?php echo $e($t('admin.login.passkey_failed')) ?>"
            ><?php echo $e($t('admin.login.passkey_continue')) ?></button>
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <?php endif; ?>
        <?php if (!empty($totpEnabled)): ?>
            <?php if (!empty($passkeyEnabled)): ?><div class="sso-divider"><?php echo $e($t('admin.login.oidc_or')) ?></div><?php endif; ?>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/login/2fa" style="background:transparent;padding:0;border:0;border-radius:0">
                <p class="login-hint"><?php echo $e($t('admin.login.2fa_hint')) ?></p>
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <label for="code"><?php echo $e($t('admin.login.2fa_code')) ?></label>
                <input id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" <?php echo empty($passkeyEnabled) ? 'required autofocus' : 'required' ?>>
                <button type="submit"><?php echo $e($t('admin.login.2fa_submit')) ?></button>
            </form>
        <?php elseif (empty($passkeyEnabled)): ?>
            <p class="error"><?php echo $e($t('admin.login.2fa_invalid')) ?></p>
        <?php endif; ?>
    </div>
    <p class="login-foot">
        <a href="<?php echo $e($basePath) ?>/admin/login"><?php echo $e($t('admin.login.2fa_back')) ?></a>
        | <?php echo $e($t('admin.login.tagline')) ?>
    </p>
</div>
<?php if (!empty($passkeyEnabled)): ?>
<script src="<?php echo $e($basePath) ?>/assets/admin/passkeys.js" defer></script>
<?php endif; ?>
</body>
</html>
