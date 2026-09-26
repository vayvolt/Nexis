<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$lang = \Nexis\I18n\Translator::normalizeUiLocale((string) ($uiLocale ?? 'de'));
$langQ = 'lang=' . rawurlencode($lang);
?>
<div class="card">
    <h1><?php echo $e($t('account.2fa.title')) ?></h1>
    <p class="muted"><?php echo $e($t('account.2fa.hint')) ?></p>
    <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
    <form method="post" action="<?php echo $e($basePath) ?>/account/login/2fa">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="lang" value="<?php echo $e($lang) ?>">
        <label for="code"><?php echo $e($t('account.2fa.code')) ?></label>
        <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.2fa.submit')) ?></button></p>
    </form>
    <p class="links muted"><a href="<?php echo $e($basePath) ?>/account/login?<?php echo $e($langQ) ?>"><?php echo $e($t('account.2fa.back')) ?></a></p>
</div>
