<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$lang = \Nexis\I18n\Translator::normalizeUiLocale((string) ($uiLocale ?? 'de'));
$langQ = 'lang=' . rawurlencode($lang);
?>
<div class="card">
    <h1><?php echo $e($t('account.login.title')) ?></h1>
    <?php if (!empty($deleted)): ?><p class="flash"><?php echo $e($t('account.login.deleted')) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
    <form method="post" action="<?php echo $e($basePath) ?>/account/login">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="lang" value="<?php echo $e($lang) ?>">
        <label for="email"><?php echo $e($t('admin.common.email')) ?></label>
        <input id="email" name="email" type="email" required autocomplete="username">
        <label for="password"><?php echo $e($t('admin.common.password')) ?></label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.login.submit')) ?></button></p>
    </form>
    <p class="links muted">
        <?php if (!empty($resetEnabled)): ?>
            <a href="<?php echo $e($basePath) ?>/account/password/forgot?<?php echo $e($langQ) ?>"><?php echo $e($t('account.login.forgot')) ?></a>
        <?php endif; ?>
        <?php if (!empty($registrationEnabled)): ?>
            <?php if (!empty($resetEnabled)): ?> · <?php endif; ?>
            <a href="<?php echo $e($basePath) ?>/account/register?<?php echo $e($langQ) ?>"><?php echo $e($t('account.login.create')) ?></a>
        <?php endif; ?>
    </p>
</div>
