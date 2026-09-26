<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<array{time: string, level: string, message: string, context: string, channel: string}> $entries */
$entries = $entries ?? [];
$filterLevel = (string) ($filterLevel ?? '');
$limit = (int) ($limit ?? 150);
$fileSize = (int) ($fileSize ?? 0);
$logPath = (string) ($logPath ?? 'storage/logs/app.log');
$levels = ['', 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'];
?>
<h1><?php echo $e($t('admin.logs.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.logs.intro', ['path' => $logPath])) ?></p>

<form method="get" action="<?php echo $e($basePath) ?>/admin/logs" class="card" style="padding:1rem;margin-bottom:1rem">
    <div class="row" style="align-items:flex-end;margin:0">
        <div>
            <label for="level"><?php echo $e($t('admin.logs.filter_level')) ?></label>
            <select id="level" name="level">
                <?php foreach ($levels as $lvl): ?>
                    <option value="<?php echo $e($lvl) ?>" <?php echo $filterLevel === $lvl ? 'selected' : '' ?>>
                        <?php echo $e($lvl === '' ? $t('admin.logs.all_levels') : $lvl) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="limit"><?php echo $e($t('admin.logs.limit')) ?></label>
            <select id="limit" name="limit">
                <?php foreach ([50, 100, 150, 250, 500] as $n): ?>
                    <option value="<?php echo $n ?>" <?php echo $limit === $n ? 'selected' : '' ?>><?php echo $n ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn"><?php echo $e($t('admin.common.filter')) ?></button>
        </div>
    </div>
    <p class="muted" style="margin:.65rem 0 0;font-size:.85rem">
        <?php echo $e($t('admin.logs.file_size', ['size' => number_format($fileSize / 1024, 1) . ' KB'])) ?>
    </p>
</form>

<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead>
            <tr>
                <th style="width:11rem"><?php echo $e($t('admin.logs.col_time')) ?></th>
                <th style="width:6rem"><?php echo $e($t('admin.logs.col_level')) ?></th>
                <th><?php echo $e($t('admin.logs.col_message')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($entries === []): ?>
                <tr><td colspan="3" class="muted"><?php echo $e($t('admin.logs.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $entry): ?>
                <?php
                $lvl = strtoupper($entry['level']);
                $badge = match (true) {
                    in_array($lvl, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true) => 'badge-editor',
                    $lvl === 'WARNING' => 'badge-editor',
                    default => 'badge-admin',
                };
                ?>
                <tr>
                    <td><code style="font-size:.78rem"><?php echo $e($entry['time'] !== '' ? $entry['time'] : '—') ?></code></td>
                    <td><span class="badge <?php echo $e($badge) ?>"><?php echo $e($lvl) ?></span></td>
                    <td>
                        <div><?php echo $e($entry['message']) ?></div>
                        <?php if ($entry['context'] !== ''): ?>
                            <pre class="muted" style="margin:.35rem 0 0;font-size:.75rem;white-space:pre-wrap;word-break:break-word"><?php echo $e($entry['context']) ?></pre>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
