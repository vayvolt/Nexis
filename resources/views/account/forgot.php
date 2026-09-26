<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$lang = \Nexis\I18n\Translator::normalizeUiLocale((string) ($uiLocale ?? 'de'));
$langQ = 'lang=' . rawurlencode($lang);
?>
<div class="card">
    <h1><?php echo $e($t('account.forgot.title')) ?></h1>
    <?php if (!empty($sent)): ?>
        <p class="flash"><?php echo $e($t('account.forgot.sent')) ?></p>
    <?php endif; ?>
    <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
    <form method="post" action="<?php echo $e($basePath) ?>/account/password/forgot">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="lang" value="<?php echo $e($lang) ?>">
        <label for="email"><?php echo $e($t('admin.common.email')) ?></label>
        <input id="email" name="email" type="email" required autocomplete="username">
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.forgot.submit')) ?></button></p>
    </form>
    <p class="links muted"><a href="<?php echo $e($basePath) ?>/account/login?<?php echo $e($langQ) ?>"><?php echo $e($t('account.forgot.back')) ?></a></p>
</div>
