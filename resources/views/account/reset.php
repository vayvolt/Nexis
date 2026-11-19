<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<div class="card">
    <h1><?php echo $e($t('account.reset.title')) ?></h1>
    <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
    <form method="post" action="<?php echo $e($basePath) ?>/account/password/reset">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="token" value="<?php echo $e($token) ?>">
        <label for="password"><?php echo $e($t('account.dashboard.new_password')) ?></label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        <label for="password_confirmation"><?php echo $e($t('account.reset.confirm')) ?></label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.reset.submit')) ?></button></p>
    </form>
</div>
