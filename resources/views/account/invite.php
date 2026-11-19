<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<div class="card">
    <h1><?php echo $e($t('account.invite.title')) ?></h1>
    <?php if ($invite === null): ?>
        <p class="error"><?php echo $e($error !== '' ? $error : $t('account.invite.invalid')) ?></p>
        <p class="links muted"><a href="<?php echo $e($basePath) ?>/account/login"><?php echo $e($t('account.nav.login')) ?></a></p>
    <?php else: ?>
        <p class="muted"><?php echo $e($t('account.invite.intro', ['email' => $invite['email'], 'site' => $site->name ?? 'Nexis'])) ?></p>
        <?php if ($error !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>
        <form method="post" action="<?php echo $e($basePath) ?>/account/invite">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <input type="hidden" name="token" value="<?php echo $e($token) ?>">
            <label for="display_name"><?php echo $e($t('account.dashboard.display_name')) ?></label>
            <input id="display_name" name="display_name" type="text" required maxlength="190" value="<?php echo $e($invite['display_name']) ?>" autocomplete="name">
            <label for="password"><?php echo $e($t('account.dashboard.new_password')) ?></label>
            <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
            <label for="password_confirmation"><?php echo $e($t('account.reset.confirm')) ?></label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
            <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.invite.submit')) ?></button></p>
        </form>
    <?php endif; ?>
</div>
