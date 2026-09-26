<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<array{id:int, action:string, entity_type:?string, entity_id:?string, actor_name:?string, ip:?string, context:?array<string, mixed>, created_at:string}> $entries */
/** @var list<string> $actions */
?>
<h1><?php echo $e($t('admin.audit.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.audit.intro', ['site' => $site->name])) ?></p>

<div class="card">
    <form method="get" action="<?php echo $e($basePath) ?>/admin/audit" class="row" style="align-items:flex-end;margin:0">
        <div>
            <label for="action"><?php echo $e($t('admin.audit.filter')) ?></label>
            <select id="action" name="action">
                <option value=""><?php echo $e($t('admin.common.all')) ?></option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?php echo $e($action) ?>" <?php echo ($filterAction ?? '') === $action ? 'selected' : '' ?>><?php echo $e($action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:0">
            <button type="submit"><?php echo $e($t('admin.common.filter')) ?></button>
            <?php if (($filterAction ?? '') !== ''): ?>
                <a class="btn" href="<?php echo $e($basePath) ?>/admin/audit" style="background:#78716c;margin-left:.35rem"><?php echo $e($t('admin.common.reset')) ?></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.audit.time')) ?></th>
                <th><?php echo $e($t('admin.audit.action')) ?></th>
                <th><?php echo $e($t('admin.audit.actor')) ?></th>
                <th><?php echo $e($t('admin.audit.object')) ?></th>
                <th><?php echo $e($t('admin.audit.details')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($entries === []): ?>
                <tr><td colspan="5" class="muted"><?php echo $e($t('admin.audit.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $entry): ?>
                <?php
                try {
                    $when = (new DateTimeImmutable($entry['created_at'], new DateTimeZone('UTC')))
                        ->setTimezone(new DateTimeZone('Europe/Berlin'))
                        ->format('d.m.Y H:i:s');
                } catch (Exception) {
                    $when = $entry['created_at'];
                }
                $object = trim(($entry['entity_type'] ?? '') . ' ' . ($entry['entity_id'] ?? ''));
                $details = '';
                if (is_array($entry['context']) && $entry['context'] !== []) {
                    $details = json_encode($entry['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                }
                ?>
                <tr>
                    <td style="white-space:nowrap"><?php echo $e($when) ?></td>
                    <td><code><?php echo $e($entry['action']) ?></code></td>
                    <td><?php echo $e($entry['actor_name'] ?? $t('admin.common.empty_dash')) ?></td>
                    <td class="muted"><?php echo $e($object !== '' ? $object : $t('admin.common.empty_dash')) ?></td>
                    <td class="muted" style="max-width:28rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?php echo $e($details) ?>"><?php echo $e($details !== '' ? $details : $t('admin.common.empty_dash')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
