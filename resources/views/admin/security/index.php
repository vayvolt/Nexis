<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;

$onOff = static function (bool $on) use ($t): array {
    return $on
        ? ['class' => 'badge-admin', 'label' => $t('admin.security.status_on')]
        : ['class' => 'badge-editor', 'label' => $t('admin.security.status_off')];
};

$ssoReady = !empty($ssoReady);
$ssoToggle = !empty($ssoToggle);
$ssoBadge = $onOff($ssoReady);
$regBadge = $onOff(!empty($registrationEnabled));
$resetBadge = $onOff(!empty($passwordResetEnabled));
$debugOn = !empty($appDebug);
$debugBadge = $debugOn
    ? ['class' => 'badge-editor', 'label' => $t('admin.security.debug_on')]
    : ['class' => 'badge-admin', 'label' => $t('admin.security.debug_off')];

$ssoHint = '';
if ($ssoToggle && !$ssoReady) {
    $ssoHint = $t('admin.security.sso_incomplete');
} elseif ($ssoReady) {
    $ssoHint = $t('admin.security.sso_ready_hint');
} else {
    $ssoHint = $t('admin.security.sso_off_hint');
}
?>
<h1><?php echo $e($t('admin.security.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.security.intro')) ?></p>

<h2 class="security-section-title"><?php echo $e($t('admin.security.overview')) ?></h2>
<div class="dash-grid security-status-grid">
    <section class="card dash-card">
        <h3><?php echo $e($t('admin.security.sso')) ?></h3>
        <p><span class="badge <?php echo $e($ssoBadge['class']) ?>"><?php echo $e($ssoBadge['label']) ?></span></p>
        <p class="muted security-card-hint"><?php echo $e($ssoHint) ?></p>
        <?php if (!empty($canSettings)): ?>
            <p class="dash-links"><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/settings#sso"><?php echo $e($t('admin.security.link_sso')) ?></a></p>
        <?php endif; ?>
    </section>

    <section class="card dash-card">
        <h3><?php echo $e($t('admin.security.registration')) ?></h3>
        <p><span class="badge <?php echo $e($regBadge['class']) ?>"><?php echo $e($regBadge['label']) ?></span></p>
        <p class="muted security-card-hint"><?php echo $e($t('admin.security.registration_hint')) ?></p>
        <?php if (!empty($canSettings)): ?>
            <p class="dash-links"><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/settings#members"><?php echo $e($t('admin.security.link_members')) ?></a></p>
        <?php endif; ?>
    </section>

    <section class="card dash-card">
        <h3><?php echo $e($t('admin.security.password_reset')) ?></h3>
        <p><span class="badge <?php echo $e($resetBadge['class']) ?>"><?php echo $e($resetBadge['label']) ?></span></p>
        <p class="muted security-card-hint"><?php echo $e($t('admin.security.password_reset_hint')) ?></p>
        <?php if (!empty($canSettings)): ?>
            <p class="dash-links"><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/settings#members"><?php echo $e($t('admin.security.link_members')) ?></a></p>
        <?php endif; ?>
    </section>

    <section class="card dash-card">
        <h3><?php echo $e($t('admin.security.env')) ?></h3>
        <p>
            <code><?php echo $e((string) ($appEnv ?? '')) ?></code>
            <span class="badge <?php echo $e($debugBadge['class']) ?>" style="margin-left:.35rem"><?php echo $e($debugBadge['label']) ?></span>
        </p>
        <p class="muted security-card-hint"><?php echo $e($debugOn ? $t('admin.security.debug_hint_on') : $t('admin.security.debug_hint_off')) ?></p>
        <?php if (!empty($canSettings)): ?>
            <p class="dash-links"><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/health"><?php echo $e($t('admin.nav.health')) ?></a></p>
        <?php endif; ?>
    </section>

    <?php if ($pendingInvites !== null): ?>
        <section class="card dash-card">
            <h3><?php echo $e($t('admin.security.pending_invites')) ?></h3>
            <p class="security-metric"><?php echo $e((string) (int) $pendingInvites) ?></p>
            <p class="muted security-card-hint"><?php echo $e($t('admin.security.pending_invites_hint')) ?></p>
            <p class="dash-links">
                <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/users"><?php echo $e($t('admin.nav.users')) ?></a>
                <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/roles"><?php echo $e($t('admin.nav.roles')) ?></a>
            </p>
        </section>
    <?php endif; ?>

    <?php if ($activeTokens !== null): ?>
        <section class="card dash-card">
            <h3><?php echo $e($t('admin.security.active_tokens')) ?></h3>
            <p class="security-metric"><?php echo $e((string) (int) $activeTokens) ?></p>
            <p class="muted security-card-hint"><?php echo $e($t('admin.security.active_tokens_hint')) ?></p>
            <p class="dash-links"><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/api-tokens"><?php echo $e($t('admin.nav.api_tokens')) ?></a></p>
        </section>
    <?php endif; ?>

    <?php if ($webhookCount !== null): ?>
        <section class="card dash-card">
            <h3><?php echo $e($t('admin.security.webhooks')) ?></h3>
            <p class="security-metric"><?php echo $e((string) (int) $webhookCount) ?></p>
            <p class="muted security-card-hint"><?php echo $e($t('admin.security.webhooks_hint')) ?></p>
            <p class="dash-links"><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/webhooks"><?php echo $e($t('admin.nav.webhooks')) ?></a></p>
        </section>
    <?php endif; ?>

    <?php if ($lastBackupLabel !== null): ?>
        <section class="card dash-card">
            <h3><?php echo $e($t('admin.security.last_backup')) ?></h3>
            <?php if ($lastBackupLabel !== ''): ?>
                <p class="security-metric security-metric--text"><?php echo $e($lastBackupLabel) ?></p>
                <p class="muted security-card-hint"><?php echo $e($t('admin.security.last_backup_hint')) ?></p>
            <?php else: ?>
                <p><span class="badge badge-editor"><?php echo $e($t('admin.security.no_backup')) ?></span></p>
                <p class="muted security-card-hint"><?php echo $e($t('admin.security.no_backup_hint')) ?></p>
            <?php endif; ?>
            <p class="dash-links"><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/backups"><?php echo $e($t('admin.nav.backups')) ?></a></p>
        </section>
    <?php endif; ?>
</div>

<?php if (!empty($canAudit)): ?>
    <section class="card" style="margin-top:1rem">
        <h2><?php echo $e($t('admin.security.failures')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.security.failures_intro')) ?></p>
        <?php
        /** @var list<array{when: string, email: string, ip: string}> $loginFailures */
        $loginFailures = is_array($loginFailures ?? null) ? $loginFailures : [];
        ?>
        <?php if ($loginFailures === []): ?>
            <p class="muted"><?php echo $e($t('admin.security.failures_empty')) ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo $e($t('admin.audit.time')) ?></th>
                        <th><?php echo $e($t('admin.common.email')) ?></th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loginFailures as $row): ?>
                        <tr>
                            <td style="white-space:nowrap"><?php echo $e($row['when']) ?></td>
                            <td><?php echo $e($row['email'] !== '' ? $row['email'] : $t('admin.common.empty_dash')) ?></td>
                            <td><?php echo $e($row['ip'] !== '' ? $row['ip'] : $t('admin.common.empty_dash')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="dash-links" style="margin-top:1rem">
            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/audit?action=auth.login.failed"><?php echo $e($t('admin.security.link_audit_failures')) ?></a>
        </p>
    </section>
<?php endif; ?>
