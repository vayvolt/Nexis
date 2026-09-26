<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<array<string, mixed>> $submissions */
/** @var array<string, mixed>|null $selected */
/** @var list<string> $locales */
$filterQuery = static function (array $overrides = []) use ($basePath, $filterLocale, $filterStatus, $filterQ): string {
    $params = [];
    $locale = $overrides['locale'] ?? $filterLocale;
    $status = $overrides['status'] ?? $filterStatus;
    $q = $overrides['q'] ?? $filterQ;
    $view = array_key_exists('view', $overrides) ? $overrides['view'] : null;
    if (is_string($locale) && $locale !== '') {
        $params['locale'] = $locale;
    }
    if (is_string($status) && $status !== '' && $status !== 'all') {
        $params['status'] = $status;
    }
    if (is_string($q) && $q !== '') {
        $params['q'] = $q;
    }
    if (is_string($view) && $view !== '') {
        $params['view'] = $view;
    }
    $qs = http_build_query($params);

    return $basePath . '/admin/forms' . ($qs !== '' ? '?' . $qs : '');
};
?>
<h1><?php echo $e($t('admin.forms.title')) ?></h1>
<p class="muted">
    <?php echo $e($t('admin.forms.intro')) ?>
    · <?php echo $e($t('admin.forms.total', ['count' => (string) (int) $total])) ?>
    <?php if ((int) $unread > 0): ?>
        · <span class="badge badge-admin"><?php echo $e($t('admin.forms.unread', ['count' => (string) (int) $unread])) ?></span>
    <?php endif; ?>
</p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.forms.updated')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<?php if (!empty($mailNotice)): ?><p class="flash flash--info" role="status"><?php echo $e($mailNotice) ?></p><?php endif; ?>

<div class="card">
    <h2><?php echo $e($t('admin.forms.notify')) ?></h2>
    <p class="muted"><?php echo $e($t('admin.forms.notify_hint')) ?></p>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/settings" class="row" style="align-items:flex-end;margin:0">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <div>
            <label for="notify_email"><?php echo $e($t('admin.forms.recipient')) ?></label>
            <input id="notify_email" name="notify_email" type="email" value="<?php echo $e($notifyEmail ?? '') ?>" placeholder="redaktion@example.com">
        </div>
        <div style="flex:0">
            <button type="submit"><?php echo $e($t('admin.common.save')) ?></button>
        </div>
    </form>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/test-mail" style="margin:.75rem 0 0">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="notify_email" value="<?php echo $e($notifyEmail ?? '') ?>">
        <button type="submit" class="btn-small"><?php echo $e($t('admin.forms.test_mail')) ?></button>
    </form>
</div>

<div class="card">
    <form method="get" action="<?php echo $e($basePath) ?>/admin/forms" class="row" style="align-items:flex-end;margin:0">
        <div>
            <label for="q"><?php echo $e($t('admin.forms.search')) ?></label>
            <input id="q" name="q" value="<?php echo $e($filterQ) ?>" placeholder="<?php echo $e($t('admin.forms.search_placeholder')) ?>">
        </div>
        <div style="flex:0 0 8rem">
            <label for="locale">Locale</label>
            <select id="locale" name="locale">
                <option value=""><?php echo $e($t('admin.common.all')) ?></option>
                <?php foreach ($locales as $loc): ?>
                    <option value="<?php echo $e($loc) ?>" <?php echo $filterLocale === $loc ? 'selected' : '' ?>><?php echo $e($loc) ?></option>
                <?php endforeach; ?>
                <?php foreach ($site->enabledLocales() as $loc): ?>
                    <?php if (in_array($loc->locale, $locales, true)) { continue; } ?>
                    <option value="<?php echo $e($loc->locale) ?>" <?php echo $filterLocale === $loc->locale ? 'selected' : '' ?>><?php echo $e($loc->locale) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:0 0 9rem">
            <label for="status"><?php echo $e($t('admin.common.status')) ?></label>
            <select id="status" name="status">
                <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : '' ?>><?php echo $e($t('admin.common.all')) ?></option>
                <option value="unread" <?php echo $filterStatus === 'unread' ? 'selected' : '' ?>><?php echo $e($t('admin.forms.status_unread')) ?></option>
                <option value="read" <?php echo $filterStatus === 'read' ? 'selected' : '' ?>><?php echo $e($t('admin.forms.status_read')) ?></option>
            </select>
        </div>
        <div style="flex:0">
            <button type="submit"><?php echo $e($t('admin.common.filter')) ?></button>
            <?php if ($filterQ !== '' || $filterLocale !== '' || $filterStatus !== 'all'): ?>
                <a class="btn" href="<?php echo $e($basePath) ?>/admin/forms" style="background:#78716c;margin-left:.35rem"><?php echo $e($t('admin.common.reset')) ?></a>
            <?php endif; ?>
        </div>
    </form>
    <?php if ((int) $unread > 0): ?>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/read-all" style="margin:.85rem 0 0;display:inline-block">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <button type="submit" class="btn-small"><?php echo $e($t('admin.forms.mark_all_read')) ?></button>
        </form>
    <?php endif; ?>
    <p style="margin:.85rem 0 0">
        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/forms/export"><?php echo $e($t('admin.forms.export_csv')) ?></a>
    </p>
</div>

<?php if (is_array($selected)): ?>
    <?php
    try {
        $when = (new DateTimeImmutable((string) $selected['created_at'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))
            ->format('d.m.Y H:i:s');
    } catch (Exception) {
        $when = (string) $selected['created_at'];
    }
    $isRead = ($selected['read_at'] ?? null) !== null && $selected['read_at'] !== '';
    ?>
    <div class="card" id="form-detail">
        <div class="row" style="align-items:flex-start;margin:0">
            <div>
                <h2 style="margin:0"><?php echo $e((string) $selected['name']) ?></h2>
                <p class="muted" style="margin:.35rem 0 0">
                    <?php echo $e($when) ?>
                    · Locale <?php echo $e((string) $selected['locale']) ?>
                    · <?php echo $isRead ? $e($t('admin.forms.read')) : $e($t('admin.forms.unread_label')) ?>
                </p>
            </div>
            <div style="flex:0;text-align:right">
                <a class="btn btn-small" href="mailto:<?php echo $e((string) $selected['email']) ?>?subject=<?php echo $e(rawurlencode('Re: Ihre Nachricht')) ?>"><?php echo $e($t('admin.forms.reply')) ?></a>
                <a class="btn btn-small" href="<?php echo $e($filterQuery(['view' => ''])) ?>" style="background:#78716c"><?php echo $e($t('admin.forms.close')) ?></a>
            </div>
        </div>
        <p style="margin:1rem 0 .35rem"><strong><?php echo $e($t('admin.common.email')) ?>:</strong> <a href="mailto:<?php echo $e((string) $selected['email']) ?>"><?php echo $e((string) $selected['email']) ?></a></p>
        <div class="forms-message"><?php echo nl2br($e((string) $selected['message'])) ?></div>
        <div class="media-actions" style="margin-top:1rem">
            <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/read">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <input type="hidden" name="id" value="<?php echo $e((string) $selected['id']) ?>">
                <input type="hidden" name="read" value="<?php echo $isRead ? '0' : '1' ?>">
                <button type="submit" class="btn-small"><?php echo $isRead ? $e($t('admin.forms.mark_unread')) : $e($t('admin.forms.mark_read')) ?></button>
            </form>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/delete" onsubmit="return confirm(<?php echo json_encode($t('admin.forms.delete_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <input type="hidden" name="id" value="<?php echo $e((string) $selected['id']) ?>">
                <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.common.delete')) ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($submissions === []): ?>
    <div class="card">
        <p class="muted" style="margin:0"><?php echo $e(($filterQ !== '' || $filterLocale !== '' || $filterStatus !== 'all') ? $t('admin.forms.empty_filter') : $t('admin.forms.empty')) ?></p>
        <?php if ((int) $total === 0): ?>
            <p class="muted"><?php echo $e($t('admin.forms.empty_hint')) ?></p>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
        <table class="forms-table">
            <thead>
                <tr>
                    <th></th>
                    <th><?php echo $e($t('admin.audit.time')) ?></th>
                    <th><?php echo $e($t('admin.forms.sender')) ?></th>
                    <th><?php echo $e($t('admin.forms.message')) ?></th>
                    <th style="width:8rem"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($submissions as $row): ?>
                    <?php
                    $id = (string) ($row['id'] ?? '');
                    $isUnread = ($row['read_at'] ?? null) === null || $row['read_at'] === '';
                    $active = is_array($selected) && (string) ($selected['id'] ?? '') === $id;
                    try {
                        $when = (new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')))
                            ->setTimezone(new DateTimeZone('Europe/Berlin'))
                            ->format('d.m.Y H:i');
                    } catch (Exception) {
                        $when = (string) $row['created_at'];
                    }
                    $preview = (string) ($row['message'] ?? '');
                    if (mb_strlen($preview) > 120) {
                        $preview = mb_substr($preview, 0, 117) . '…';
                    }
                    ?>
                    <tr class="<?php echo $active ? 'forms-row-active' : '' ?> <?php echo $isUnread ? 'forms-row-unread' : '' ?>">
                        <td><?php if ($isUnread): ?><span class="forms-dot" title="<?php echo $e($t('admin.forms.status_unread')) ?>"></span><?php endif; ?></td>
                        <td style="white-space:nowrap">
                            <?php echo $e($when) ?>
                            <div class="muted" style="font-size:.8rem"><?php echo $e((string) $row['locale']) ?></div>
                        </td>
                        <td>
                            <strong><?php echo $e((string) $row['name']) ?></strong>
                            <div><a href="mailto:<?php echo $e((string) $row['email']) ?>"><?php echo $e((string) $row['email']) ?></a></div>
                        </td>
                        <td class="muted"><?php echo $e($preview) ?></td>
                        <td>
                            <a class="btn btn-small" href="<?php echo $e($filterQuery(['view' => $id])) ?>#form-detail"><?php echo $e($t('admin.common.open')) ?></a>
                            <form method="post" action="<?php echo $e($basePath) ?>/admin/forms/delete" style="display:inline" onsubmit="return confirm(<?php echo json_encode($t('admin.forms.delete_confirm_short'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?php echo $e($id) ?>">
                                <button type="submit" class="btn-danger btn-small">×</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
