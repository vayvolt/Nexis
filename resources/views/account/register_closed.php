<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<div class="card">
    <h1><?php echo $e($t('account.register.closed_title')) ?></h1>
    <p class="muted"><?php echo $e($t('account.register.closed_body')) ?></p>
    <p class="links"><a href="<?php echo $e($basePath) ?>/account/login"><?php echo $e($t('account.register.to_login')) ?></a></p>
</div>
