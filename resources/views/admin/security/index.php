<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($t('admin.security.title')) ?></h1>
<?php if ($notice !== ''): ?><p class="flash flash--success" role="status"><?php echo $e($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<div class="card">
    <h2><?php echo $e($t('admin.security.totp')) ?></h2>
    <?php if ($enabled): ?>
        <p><?php echo $e($t('admin.security.totp_active', ['email' => $user->email])) ?></p>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/security/2fa/disable">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <p class="muted"><?php echo $e($t('admin.security.totp_disable_hint')) ?></p>
            <label for="disable_2fa_password"><?php echo $e($t('admin.security.totp_disable_password')) ?></label>
            <input id="disable_2fa_password" name="password" type="password" required autocomplete="current-password">
            <label for="disable_2fa_code"><?php echo $e($t('admin.security.totp_disable_code')) ?></label>
            <input id="disable_2fa_code" name="code" inputmode="numeric" pattern="[0-9 ]*" autocomplete="one-time-code" required>
            <button type="submit" class="btn-danger"><?php echo $e($t('admin.security.totp_disable')) ?></button>
        </form>
    <?php elseif ($pendingSecret !== ''): ?>
        <p class="muted"><?php echo $e($t('admin.security.totp_scan')) ?></p>
        <?php if (($qrSvg ?? '') !== ''): ?>
            <div class="totp-qr" aria-hidden="true"><?php echo $qrSvg ?></div>
        <?php endif; ?>
        <p class="muted"><?php echo $e($t('admin.security.totp_manual')) ?></p>
        <p><code><?php echo $e($pendingSecret) ?></code></p>
        <details>
            <summary class="muted"><?php echo $e($t('admin.security.otpauth')) ?></summary>
            <p style="word-break:break-all"><code><?php echo $e($otpauthUri) ?></code></p>
        </details>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/security/2fa/confirm">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <label for="code"><?php echo $e($t('admin.security.totp_code')) ?></label>
            <input id="code" name="code" inputmode="numeric" required>
            <button type="submit"><?php echo $e($t('admin.common.enable')) ?></button>
        </form>
    <?php else: ?>
        <p class="muted"><?php echo $e($t('admin.security.totp_protect')) ?></p>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/security/2fa/start">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <button type="submit"><?php echo $e($t('admin.security.totp_setup')) ?></button>
        </form>
    <?php endif; ?>
</div>
