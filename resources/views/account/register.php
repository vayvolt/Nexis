<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$lang = \Nexis\I18n\Translator::normalizeUiLocale((string) ($uiLocale ?? 'de'));
$langQ = 'lang=' . rawurlencode($lang);
?>
<div class="card">
    <h1><?php echo $e($t('account.register.title')) ?></h1>
    <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
    <form method="post" action="<?php echo $e($basePath) ?>/account/register">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="lang" value="<?php echo $e($lang) ?>">
        <label for="display_name"><?php echo $e($t('account.register.name')) ?></label>
        <input id="display_name" name="display_name" type="text" required maxlength="190" autocomplete="name">
        <label for="email"><?php echo $e($t('admin.common.email')) ?></label>
        <input id="email" name="email" type="email" required autocomplete="username">
        <label for="password"><?php echo $e($t('admin.common.password')) ?></label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        <p class="muted"><?php echo $e($t('account.register.password_hint')) ?></p>
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.register.submit')) ?></button></p>
    </form>
    <p class="links muted"><a href="<?php echo $e($basePath) ?>/account/login?<?php echo $e($langQ) ?>"><?php echo $e($t('account.register.have_account')) ?></a></p>
</div>
