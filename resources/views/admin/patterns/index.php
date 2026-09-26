<?php
/** @var list<\Nexis\Builder\BlockPattern> $patterns */
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($t('admin.patterns.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.patterns.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.patterns.saved')) ?></p><?php endif; ?>
<?php if (!empty($deleted)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.patterns.deleted')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<?php if ($patterns === []): ?>
    <p class="muted"><?php echo $e($t('admin.patterns.empty')) ?></p>
<?php else: ?>
    <div class="card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?php echo $e($t('admin.patterns.col_name')) ?></th>
                    <th><?php echo $e($t('admin.patterns.col_updated')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patterns as $pattern): ?>
                    <tr>
                        <td>
                            <form method="post" action="<?php echo $e($basePath) ?>/admin/patterns/rename" class="media-url-row">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <input type="hidden" name="pattern_id" value="<?php echo $e($pattern->id->value) ?>">
                                <input name="name" type="text" maxlength="190" value="<?php echo $e($pattern->name) ?>" required>
                                <button type="submit" class="btn-small"><?php echo $e($t('admin.common.save')) ?></button>
                            </form>
                        </td>
                        <td class="muted"><?php echo $e($pattern->updatedAt->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i')) ?></td>
                        <td>
                            <form
                                method="post"
                                action="<?php echo $e($basePath) ?>/admin/patterns/delete"
                                data-confirm="<?php echo $e($t('admin.patterns.delete_confirm')) ?>"
                                data-confirm-title="<?php echo $e($t('admin.patterns.delete_title')) ?>"
                                data-confirm-ok="<?php echo $e($t('admin.common.delete')) ?>"
                            >
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <input type="hidden" name="pattern_id" value="<?php echo $e($pattern->id->value) ?>">
                                <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.common.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p class="muted"><?php echo $e($t('admin.patterns.builder_hint')) ?></p>
