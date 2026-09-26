<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<\Nexis\Mail\MailLogEntry> $entries */
?>
<h1><?php echo $e($t('admin.mail.title')) ?></h1>
<p class="muted">
    <?php echo $e($t('admin.mail.intro')) ?>
    <?php echo $e($t('admin.mail.credentials_hint')) ?>
</p>

<div class="card">
    <h2><?php echo $e($t('admin.mail.transport')) ?></h2>
    <dl class="about-meta">
        <dt>Transport</dt>
        <dd><code><?php echo $e($transport) ?></code></dd>
        <dt><?php echo $e($t('admin.mail.from')) ?></dt>
        <dd><?php echo $e($mailFromName) ?> &lt;<?php echo $e($mailFrom) ?>&gt;</dd>
        <?php if ($transport === 'smtp'): ?>
            <dt>SMTP</dt>
            <dd>
                <?php echo $e($mailHost) ?>:<?php echo $e($mailPort) ?>
                · <?php echo $e($mailEncryption !== '' ? $mailEncryption : 'none') ?>
                · Auth <?php echo !empty($mailUserSet) ? $e($t('admin.mail.auth_yes')) : $e($t('admin.mail.auth_no')) ?>
            </dd>
        <?php endif; ?>
    </dl>
    <p class="muted" style="margin-top:.75rem">
        <?php echo $e($t('admin.mail.smtp_hint')) ?>
    </p>
</div>

<div class="card">
    <form method="get" action="<?php echo $e($basePath) ?>/admin/mail" class="row" style="align-items:flex-end;margin:0">
        <div>
            <label for="status"><?php echo $e($t('admin.mail.filter_status')) ?></label>
            <select id="status" name="status">
                <option value=""><?php echo $e($t('admin.common.all')) ?></option>
                <option value="sent" <?php echo ($filterStatus ?? '') === 'sent' ? 'selected' : '' ?>><?php echo $e($t('admin.mail.sent')) ?></option>
                <option value="failed" <?php echo ($filterStatus ?? '') === 'failed' ? 'selected' : '' ?>><?php echo $e($t('admin.mail.failed')) ?></option>
            </select>
        </div>
        <div style="flex:0">
            <button type="submit"><?php echo $e($t('admin.common.filter')) ?></button>
            <?php if (($filterStatus ?? '') !== ''): ?>
                <a class="btn" href="<?php echo $e($basePath) ?>/admin/mail" style="background:#78716c;margin-left:.35rem"><?php echo $e($t('admin.common.reset')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.audit.time')) ?></th>
                <th><?php echo $e($t('admin.common.status')) ?></th>
                <th>Transport</th>
                <th><?php echo $e($t('admin.mail.to')) ?></th>
                <th><?php echo $e($t('admin.mail.subject')) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($entries === []): ?>
                <tr><td colspan="6" class="muted"><?php echo $e($t('admin.mail.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td class="muted"><?php echo $e($entry->createdAt) ?></td>
                    <td>
                        <span class="badge <?php echo $entry->isFailed ? 'badge-editor' : 'badge-admin' ?>">
                            <?php echo $entry->isFailed ? $e($t('admin.mail.failed_badge')) : $e($t('admin.mail.sent_badge')) ?>
                        </span>
                    </td>
                    <td><code><?php echo $e($entry->transport) ?></code></td>
                    <td><?php echo $e($entry->toList) ?></td>
                    <td><?php echo $e($entry->subject) ?></td>
                    <td><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/mail/<?php echo $e($entry->id) ?>"><?php echo $e($t('admin.mail.details')) ?></a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
