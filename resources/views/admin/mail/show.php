<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var \Nexis\Mail\MailLogEntry $entry */
?>
<p class="muted"><a href="<?php echo $e($basePath) ?>/admin/mail"><?php echo $e($t('admin.mail.back')) ?></a></p>
<h1><?php echo $e($t('admin.mail.details_title')) ?></h1>

<div class="card">
    <dl class="about-meta">
        <dt><?php echo $e($t('admin.audit.time')) ?></dt>
        <dd><?php echo $e($entry->createdAt) ?></dd>
        <dt><?php echo $e($t('admin.common.status')) ?></dt>
        <dd>
            <span class="badge <?php echo $entry->isFailed ? 'badge-editor' : 'badge-admin' ?>">
                <?php echo $entry->isFailed ? $e($t('admin.mail.failed_badge')) : $e($t('admin.mail.sent_badge')) ?>
            </span>
        </dd>
        <dt>Transport</dt>
        <dd><code><?php echo $e($entry->transport) ?></code></dd>
        <dt><?php echo $e($t('admin.mail.from_address')) ?></dt>
        <dd><?php echo $e((string) ($entry->fromAddress ?? $t('admin.common.empty_dash'))) ?></dd>
        <dt><?php echo $e($t('admin.mail.to')) ?></dt>
        <dd><?php echo $e($entry->toList) ?></dd>
        <dt><?php echo $e($t('admin.mail.reply_to')) ?></dt>
        <dd><?php echo $e((string) ($entry->replyTo ?? $t('admin.common.empty_dash'))) ?></dd>
        <dt><?php echo $e($t('admin.mail.subject')) ?></dt>
        <dd><?php echo $e($entry->subject) ?></dd>
        <dt><?php echo $e($t('admin.mail.context')) ?></dt>
        <dd><?php echo $e((string) ($entry->context ?? $t('admin.common.empty_dash'))) ?></dd>
        <?php if ($entry->errorMessage !== null && $entry->errorMessage !== ''): ?>
            <dt><?php echo $e($t('admin.webhooks.error')) ?></dt>
            <dd class="error"><?php echo $e($entry->errorMessage) ?></dd>
        <?php endif; ?>
    </dl>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.mail.body')) ?></h2>
    <pre class="license-text"><?php echo $e($entry->bodyText) ?></pre>
</div>

<?php if ($entry->smtpLog !== null && $entry->smtpLog !== ''): ?>
<div class="card">
    <h2><?php echo $e($t('admin.mail.smtp_log')) ?></h2>
    <p class="muted"><?php echo $e($t('admin.mail.smtp_redacted')) ?></p>
    <pre class="license-text"><?php echo $e($entry->smtpLog) ?></pre>
</div>
<?php endif; ?>
