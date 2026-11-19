<?php
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$flash = match ((string) ($saved ?? '')) {
    'profile' => $t('account.dashboard.flash_profile'),
    'password' => $t('account.dashboard.flash_password'),
    '2fa' => $t('account.dashboard.flash_2fa'),
    'passkey' => $t('account.dashboard.flash_passkey'),
    'passkey_removed' => $t('account.dashboard.flash_passkey_removed'),
    '1' => $t('account.dashboard.flash_saved'),
    default => '',
};
?>
<div class="top">
    <div>
        <h1 style="margin:0"><?php echo $e($t('account.dashboard.title')) ?></h1>
        <p class="muted" style="margin:.35rem 0 0"><?php echo $e($t('account.dashboard.role', ['role' => $roleLabel])) ?></p>
    </div>
    <div class="top__actions">
        <?php if (!empty($canAccessCms)): ?>
            <a class="btn" href="<?php echo $e($basePath) ?>/admin"><?php echo $e($t('account.dashboard.open_admin')) ?></a>
        <?php endif; ?>
        <form class="top__logout" method="post" action="<?php echo $e($basePath) ?>/account/logout">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <button type="submit" class="btn btn-muted"><?php echo $e($t('account.dashboard.logout')) ?></button>
        </form>
    </div>
</div>
<?php if ($flash !== ''): ?><p class="flash"><?php echo $e($flash) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>

<nav class="account-sections" aria-label="<?php echo $e($t('account.dashboard.sections')) ?>">
    <a href="#profile"><?php echo $e($t('account.dashboard.nav_profile')) ?></a>
    <a href="#password"><?php echo $e($t('account.dashboard.nav_password')) ?></a>
    <a href="#security"><?php echo $e($t('account.dashboard.nav_security')) ?></a>
    <a href="#privacy"><?php echo $e($t('account.dashboard.nav_privacy')) ?></a>
    <?php if (!empty($canDeleteAccount)): ?><a href="#danger"><?php echo $e($t('account.dashboard.nav_account')) ?></a><?php endif; ?>
    <?php foreach ($accountPluginNav ?? [] as $navHtml): ?>
        <?php echo $navHtml ?>
    <?php endforeach; ?>
</nav>

<div class="card" id="profile">
    <h2><?php echo $e($t('account.dashboard.profile_title')) ?></h2>
    <p class="muted"><?php echo $e($t('account.dashboard.profile_intro')) ?></p>
    <form method="post" action="<?php echo $e($basePath) ?>/account/profile">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label for="display_name"><?php echo $e($t('account.dashboard.display_name')) ?></label>
        <input id="display_name" name="display_name" type="text" required maxlength="190" value="<?php echo $e($user->displayName) ?>" autocomplete="name">
        <label for="email"><?php echo $e($t('admin.common.email')) ?></label>
        <input id="email" name="email" type="email" required maxlength="190" value="<?php echo $e($user->email) ?>" autocomplete="email">
        <label for="ui_locale"><?php echo $e($t('account.dashboard.language')) ?></label>
        <select id="ui_locale" name="ui_locale">
            <?php foreach ($locales as $loc): ?>
                <?php
                $stored = $loc->label !== '' ? $loc->label : strtoupper($loc->locale);
                $label = $t('admin.locale.' . $loc->locale, [], $stored);
                ?>
                <option value="<?php echo $e($loc->locale) ?>" <?php echo $user->uiLocale === $loc->locale ? 'selected' : '' ?>>
                    <?php echo $e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.dashboard.save_profile')) ?></button></p>
    </form>
</div>

<div class="card" id="password">
    <h2><?php echo $e($t('account.dashboard.password_title')) ?></h2>
    <p class="muted"><?php echo $e($t('account.dashboard.password_intro')) ?></p>
    <form method="post" action="<?php echo $e($basePath) ?>/account/password">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label for="current_password"><?php echo $e($t('account.dashboard.current_password')) ?></label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
        <label for="password"><?php echo $e($t('account.dashboard.new_password')) ?></label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        <label for="password_confirmation"><?php echo $e($t('account.dashboard.new_password_again')) ?></label>
        <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password">
        <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.dashboard.save_password')) ?></button></p>
    </form>
</div>

<div class="card" id="security">
    <h2><?php echo $e($t('account.dashboard.security_title')) ?></h2>

    <h3 style="font-size:1.05rem;margin:0 0 .5rem"><?php echo $e($t('account.dashboard.totp_heading')) ?></h3>
    <?php if (!empty($totpEnabled)): ?>
        <p><?php echo $e($t('account.dashboard.totp_active')) ?></p>
        <form method="post" action="<?php echo $e($basePath) ?>/account/2fa/disable">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <label for="disable_2fa_password"><?php echo $e($t('account.dashboard.totp_disable_password')) ?></label>
            <input id="disable_2fa_password" name="password" type="password" required autocomplete="current-password">
            <p style="margin-top:1rem"><button type="submit" class="btn-danger"><?php echo $e($t('account.dashboard.totp_disable')) ?></button></p>
        </form>
    <?php elseif (($pendingSecret ?? '') !== ''): ?>
        <p class="muted"><?php echo $e($t('account.dashboard.totp_scan')) ?></p>
        <?php if (($qrSvg ?? '') !== ''): ?>
            <div class="totp-qr" aria-hidden="true"><?php echo $qrSvg ?></div>
        <?php endif; ?>
        <p class="muted"><?php echo $e($t('account.dashboard.totp_manual')) ?></p>
        <p><code><?php echo $e($pendingSecret) ?></code></p>
        <form method="post" action="<?php echo $e($basePath) ?>/account/2fa/confirm">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <label for="code"><?php echo $e($t('account.dashboard.totp_code')) ?></label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
            <p style="margin-top:1rem"><button type="submit"><?php echo $e($t('account.dashboard.totp_enable')) ?></button></p>
        </form>
    <?php else: ?>
        <p class="muted"><?php echo $e($t('account.dashboard.totp_protect')) ?></p>
        <form method="post" action="<?php echo $e($basePath) ?>/account/2fa/start">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <button type="submit"><?php echo $e($t('account.dashboard.totp_setup')) ?></button>
        </form>
    <?php endif; ?>

    <hr style="border:0;border-top:1px solid var(--line, #d6d3d1);margin:1.5rem 0">

    <h3 style="font-size:1.05rem;margin:0 0 .5rem"><?php echo $e($t('account.dashboard.passkeys_heading')) ?></h3>
    <p class="muted"><?php echo $e($t('account.dashboard.passkeys_intro')) ?></p>
    <?php
    /** @var list<array{id: string, name: string}> $passkeys */
    $passkeys = is_array($passkeys ?? null) ? $passkeys : [];
    ?>
    <?php if ($passkeys !== []): ?>
        <ul style="margin:0 0 1rem;padding-left:1.1rem">
            <?php foreach ($passkeys as $passkey): ?>
                <li style="margin-bottom:.5rem">
                    <?php echo $e((string) ($passkey['name'] ?? 'Passkey')) ?>
                    <form method="post" action="<?php echo $e($basePath) ?>/account/passkeys/delete" style="display:inline;margin-left:.5rem">
                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                        <input type="hidden" name="credential_id" value="<?php echo $e((string) ($passkey['id'] ?? '')) ?>">
                        <button type="submit" class="btn btn-small btn-danger"><?php echo $e($t('account.dashboard.passkeys_delete')) ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted"><?php echo $e($t('account.dashboard.passkeys_empty')) ?></p>
    <?php endif; ?>
    <button
        type="button"
        class="btn"
        data-passkey-register
        data-base-path="<?php echo $e($basePath) ?>"
        data-options-url="<?php echo $e($basePath) ?>/account/passkeys/options"
        data-register-url="<?php echo $e($basePath) ?>/account/passkeys/register"
        data-error="<?php echo $e($t('account.dashboard.passkeys_failed')) ?>"
    ><?php echo $e($t('account.dashboard.passkeys_add')) ?></button>
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
    <script src="<?php echo $e($basePath) ?>/assets/admin/passkeys.js" defer></script>
</div>

<div class="card" id="privacy">
    <h2><?php echo $e($t('account.dashboard.privacy_title')) ?></h2>
    <p class="muted"><?php echo $e($t('account.dashboard.privacy_intro')) ?></p>
    <p><a class="btn" href="<?php echo $e($basePath) ?>/account/export"><?php echo $e($t('account.dashboard.privacy_export')) ?></a></p>
</div>

<?php if (!empty($canDeleteAccount)): ?>
<div class="card card-danger" id="danger">
    <h2><?php echo $e($t('account.dashboard.delete_title')) ?></h2>
    <p class="muted"><?php echo $e($t('account.dashboard.delete_intro')) ?></p>
    <button type="button" class="btn-danger" data-account-delete-open><?php echo $e($t('account.dashboard.delete_open')) ?></button>
</div>

<dialog class="account-modal" id="account-delete-modal" aria-labelledby="account-delete-title">
    <form method="post" action="<?php echo $e($basePath) ?>/account/delete">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <h2 id="account-delete-title"><?php echo $e($t('account.dashboard.delete_confirm_title')) ?></h2>
        <p class="muted"><?php echo $e($t('account.dashboard.delete_irreversible')) ?></p>
        <label for="delete_password"><?php echo $e($t('admin.common.password')) ?></label>
        <input id="delete_password" name="password" type="password" required autocomplete="current-password">
        <label for="delete_confirm"><?php echo $e($t('account.dashboard.delete_type')) ?></label>
        <input id="delete_confirm" name="confirm" type="text" required autocomplete="off" placeholder="DELETE">
        <div class="account-modal__actions">
            <button type="button" class="btn btn-muted" data-account-delete-cancel><?php echo $e($t('account.dashboard.delete_cancel')) ?></button>
            <button type="submit" class="btn-danger"><?php echo $e($t('account.dashboard.delete_final')) ?></button>
        </div>
    </form>
</dialog>
<script src="<?php echo $e($basePath) ?>/assets/account/settings.js" defer></script>
<?php endif; ?>

<p class="links muted"><a href="<?php echo $e($basePath) ?>/"><?php echo $e($t('account.dashboard.back_site')) ?></a></p>
