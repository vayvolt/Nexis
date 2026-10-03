<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<array{id: string, name: string, slug: string}> $roles */
/** @var list<string> $permissions */
/** @var array<string, array<string, true>> $grants */
$roles = $roles ?? [];
$permissions = $permissions ?? [];
$grants = $grants ?? [];
?>
<p class="muted" style="margin:.35rem 0 1rem">
    <a class="btn btn-small btn-muted" href="<?php echo $e($basePath) ?>/admin/users"><?php echo $e($t('admin.roles.tab_users')) ?></a>
    <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/roles"><?php echo $e($t('admin.roles.tab_matrix')) ?></a>
</p>
<h1><?php echo $e($t('admin.roles.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.roles.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.roles.saved')) ?></p><?php endif; ?>
<?php if (!empty($reset)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.roles.reset_done')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<form method="post" action="<?php echo $e($basePath) ?>/admin/roles">
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
    <div class="card" style="padding:0;overflow:auto">
        <table class="roles-matrix">
            <thead>
                <tr>
                    <th style="min-width:14rem"><?php echo $e($t('admin.roles.permission')) ?></th>
                    <?php foreach ($roles as $role): ?>
                        <th style="text-align:center;white-space:nowrap">
                            <?php echo $e($role['name']) ?>
                            <div class="muted" style="font-size:.75rem;font-weight:400"><?php echo $e($role['slug']) ?></div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($permissions === []): ?>
                    <tr><td colspan="<?php echo (string) (count($roles) + 1) ?>" class="muted"><?php echo $e($t('admin.roles.empty')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($permissions as $key): ?>
                    <?php
                    $label = $t('admin.permission.' . $key, [], $key);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo $e($label) ?></strong>
                            <div class="muted" style="font-size:.8rem"><code><?php echo $e($key) ?></code></div>
                        </td>
                        <?php foreach ($roles as $role): ?>
                            <?php
                            $checked = !empty($grants[$role['id']][$key]);
                            $locked = $role['slug'] === \Nexis\Auth\RoleSlug::ADMIN;
                            ?>
                            <td style="text-align:center">
                                <?php if ($locked): ?>
                                    <input type="checkbox" checked disabled title="<?php echo $e($t('admin.roles.admin_locked')) ?>" aria-label="<?php echo $e($role['name'] . ': ' . $key) ?>">
                                <?php else: ?>
                                    <input
                                        type="checkbox"
                                        name="perm[<?php echo $e($role['id']) ?>][<?php echo $e($key) ?>]"
                                        value="1"
                                        <?php echo $checked ? 'checked' : '' ?>
                                        aria-label="<?php echo $e($role['name'] . ': ' . $key) ?>"
                                    >
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
        <button type="submit" name="action" value="save"><?php echo $e($t('admin.common.save')) ?></button>
        <button
            type="submit"
            name="action"
            value="reset"
            class="btn-muted"
            onclick="return confirm(<?php echo json_encode($t('admin.roles.reset_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);"
        ><?php echo $e($t('admin.roles.reset')) ?></button>
    </p>
</form>

<style>
.roles-matrix { width: 100%; border-collapse: collapse; }
.roles-matrix th, .roles-matrix td { padding: .65rem .75rem; border-bottom: 1px solid var(--line); vertical-align: middle; }
.roles-matrix thead th { position: sticky; top: 0; background: var(--card); z-index: 1; }
.roles-matrix tbody tr:hover { background: #fafaf9; }
.roles-matrix input[type=checkbox] { width: 1.1rem; height: 1.1rem; }
</style>
