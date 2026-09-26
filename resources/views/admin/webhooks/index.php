<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($t('admin.webhooks.title')) ?></h1>
<?php if ($notice !== ''): ?><p class="flash flash--success" role="status"><?php echo $e($notice) ?></p><?php endif; ?>
<p class="muted"><?php echo $e($t('admin.webhooks.intro')) ?> <?php echo $e($t('admin.webhooks.signature')) ?></p>

<div class="card">
    <h2><?php echo $e($t('admin.webhooks.new')) ?></h2>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/webhooks">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label for="url">URL</label>
        <input id="url" name="url" type="url" required placeholder="https://example.com/hook">
        <?php foreach (($webhookEvents ?? ['page.published', 'page.unpublished', 'plugin.enabled', 'plugin.disabled']) as $eventName): ?>
            <?php
            $field = 'event_' . str_replace('.', '_', (string) $eventName);
            $checked = $eventName === 'page.published' ? ' checked' : '';
            ?>
            <label><input type="checkbox" name="<?php echo $e($field) ?>" value="1"<?php echo $checked ?>> <?php echo $e((string) $eventName) ?></label>
        <?php endforeach; ?>
        <p><button type="submit"><?php echo $e($t('admin.common.create')) ?></button></p>
    </form>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.webhooks.active')) ?></h2>
    <?php if ($endpoints === []): ?>
        <p class="muted"><?php echo $e($t('admin.webhooks.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead><tr><th>URL</th><th>Events</th><th>Secret</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($endpoints as $endpoint): ?>
                <tr>
                    <td><?php echo $e($endpoint->url) ?></td>
                    <td><?php echo $e(implode(', ', $endpoint->events)) ?></td>
                    <td><code><?php echo $e(substr($endpoint->secret, 0, 8)) ?>…</code></td>
                    <td>
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/webhooks/delete">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <input type="hidden" name="id" value="<?php echo $e($endpoint->id) ?>">
                            <button type="submit"><?php echo $e($t('admin.common.delete')) ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.webhooks.deliveries')) ?></h2>
    <?php if (($deliveries ?? []) === []): ?>
        <p class="muted"><?php echo $e($t('admin.webhooks.deliveries_empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?php echo $e($t('admin.audit.time')) ?></th>
                <th><?php echo $e($t('admin.webhooks.event')) ?></th>
                <th><?php echo $e($t('admin.common.status')) ?></th>
                <th><?php echo $e($t('admin.webhooks.http')) ?></th>
                <th>URL</th>
                <th><?php echo $e($t('admin.webhooks.error')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($deliveries as $row): ?>
                <tr>
                    <td><?php echo $e($row['created_at']) ?></td>
                    <td><code><?php echo $e($row['event_name']) ?></code></td>
                    <td><?php echo $e($row['status']) ?></td>
                    <td><?php echo $row['http_status'] !== null ? $e((string) $row['http_status']) : $e($t('admin.common.empty_dash')) ?></td>
                    <td><?php echo $e((string) ($row['url'] ?? $t('admin.common.empty_dash'))) ?></td>
                    <td class="muted"><?php echo $e((string) ($row['error'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
