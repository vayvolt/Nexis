<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<array{user:\Nexis\Auth\User, role_id:string, role_name:string, role_slug:string}> $members */
$editId = is_string($_GET['edit'] ?? null) ? (string) $_GET['edit'] : '';
$openNewUser = (($_GET['new'] ?? '') === '1');
?>
<h1><?php echo $e($t('admin.users.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.users.intro')) ?></p>
<?php if (!empty($saved)): ?><p class="flash flash--success" role="status"><?php echo $e($t('admin.common.saved')) ?></p><?php endif; ?>
<?php if (($error ?? '') !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>

<?php if (!empty($canManage)): ?>
    <p class="dash-links" style="margin:0 0 1rem">
        <button type="button" class="btn btn-small" data-user-create-open><?php echo $e($t('admin.users.new')) ?></button>
    </p>
<?php endif; ?>

<div class="card">
    <table>
        <thead>
            <tr>
                <th><?php echo $e($t('admin.common.name')) ?></th>
                <th><?php echo $e($t('admin.common.email')) ?></th>
                <th><?php echo $e($t('admin.common.role')) ?></th>
                <th><?php echo $e($t('admin.users.rights')) ?></th>
                <?php if (!empty($canManage)): ?><th style="width:11rem"><?php echo $e($t('admin.common.actions')) ?></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($members === []): ?>
                <tr><td colspan="<?php echo !empty($canManage) ? 5 : 4 ?>"><?php echo $e($t('admin.users.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($members as $member): ?>
                <?php $m = $member['user']; ?>
                <tr>
                    <td><strong><?php echo $e($m->displayName) ?></strong></td>
                    <td><?php echo $e($m->email) ?></td>
                    <td><?php echo $e($member['role_name']) ?></td>
                    <td>
                        <?php if ($m->isPlatformAdmin): ?>
                            <span class="badge badge-admin"><?php echo $e($t('admin.users.platform_admin')) ?></span>
                        <?php else: ?>
                            <span class="muted"><?php echo $e($t('admin.users.member')) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if (!empty($canManage)): ?>
                        <td>
                            <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/users?edit=<?php echo $e($m->id->value) ?>#edit-user"><?php echo $e($t('admin.common.edit')) ?></a>
                            <?php if ($m->id->value !== $user->id->value): ?>
                                <button
                                    type="button"
                                    class="btn-danger btn-small"
                                    data-user-delete
                                    data-user-id="<?php echo $e($m->id->value) ?>"
                                    data-user-label="<?php echo $e($m->displayName . ' (' . $m->email . ')') ?>"
                                ><?php echo $e($t('admin.common.delete')) ?></button>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if (!empty($canManage)): ?>
    <?php
    $editMember = null;
    foreach ($members as $member) {
        if ($member['user']->id->value === $editId) {
            $editMember = $member;
            break;
        }
    }
    ?>

    <?php if ($editMember !== null): ?>
        <?php
        $em = $editMember['user'];
        $editingSelf = $em->id->value === $user->id->value;
        $lockAdminRights = $editingSelf && ($em->isPlatformAdmin || $editMember['role_slug'] === 'admin');
        ?>
        <div class="card" id="edit-user">
            <h2><?php echo $e($t('admin.users.edit')) ?></h2>
            <p class="muted"><?php echo $e($em->email) ?></p>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/users/update">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <input type="hidden" name="user_id" value="<?php echo $e($em->id->value) ?>">
                <div class="row">
                    <div>
                        <label for="edit_display_name"><?php echo $e($t('admin.common.name')) ?></label>
                        <input id="edit_display_name" name="display_name" value="<?php echo $e($em->displayName) ?>" required>
                    </div>
                    <div>
                        <label for="edit_email"><?php echo $e($t('admin.common.email')) ?></label>
                        <input id="edit_email" name="email" type="email" value="<?php echo $e($em->email) ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label for="edit_role_id"><?php echo $e($t('admin.users.site_role')) ?></label>
                        <?php if ($lockAdminRights && $editMember['role_slug'] === 'admin'): ?>
                            <input type="hidden" name="role_id" value="<?php echo $e($editMember['role_id']) ?>">
                            <select id="edit_role_id" disabled aria-disabled="true">
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $e($role['id']) ?>" <?php echo $editMember['role_id'] === $role['id'] ? 'selected' : '' ?>><?php echo $e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <select id="edit_role_id" name="role_id">
                                <option value=""><?php echo $e($t('admin.users.no_membership')) ?></option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $e($role['id']) ?>" <?php echo $editMember['role_id'] === $role['id'] ? 'selected' : '' ?>><?php echo $e($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="edit_password"><?php echo $e($t('admin.users.new_password')) ?></label>
                        <input id="edit_password" name="password" type="password" minlength="8" placeholder="<?php echo $e($t('admin.users.password_unchanged')) ?>" autocomplete="new-password">
                    </div>
                </div>
                <?php if ($lockAdminRights && $em->isPlatformAdmin): ?>
                    <input type="hidden" name="is_platform_admin" value="1">
                    <label style="margin-top:1rem"><input type="checkbox" checked disabled aria-disabled="true" style="width:auto"> <?php echo $e($t('admin.users.platform_admin')) ?></label>
                <?php else: ?>
                    <label style="margin-top:1rem"><input type="checkbox" name="is_platform_admin" value="1" <?php echo $em->isPlatformAdmin ? 'checked' : '' ?> style="width:auto"> <?php echo $e($t('admin.users.platform_admin')) ?></label>
                <?php endif; ?>
                <?php if ($lockAdminRights): ?>
                    <p class="muted" style="margin-top:.65rem"><?php echo $e($t('admin.users.self_admin_locked')) ?></p>
                <?php endif; ?>
                <p style="margin-top:1rem">
                    <button type="submit"><?php echo $e($t('admin.common.save')) ?></button>
                    <a class="btn" href="<?php echo $e($basePath) ?>/admin/users" style="background:#78716c;margin-left:.35rem"><?php echo $e($t('admin.common.cancel')) ?></a>
                </p>
            </form>
        </div>
    <?php endif; ?>

    <dialog
        class="admin-modal admin-modal--wide"
        id="user-create-modal"
        aria-labelledby="user-create-title"
        <?php echo $openNewUser ? 'data-user-create-autoopen' : '' ?>
    >
        <form method="post" action="<?php echo $e($basePath) ?>/admin/users" id="user-create-form">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <h2 id="user-create-title"><?php echo $e($t('admin.users.new')) ?></h2>
            <div class="row">
                <div>
                    <label for="display_name"><?php echo $e($t('admin.common.name')) ?></label>
                    <input id="display_name" name="display_name" required>
                </div>
                <div>
                    <label for="email"><?php echo $e($t('admin.common.email')) ?></label>
                    <input id="email" name="email" type="email" required>
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="password"><?php echo $e($t('admin.common.password')) ?></label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
                </div>
                <div>
                    <label for="role_id"><?php echo $e($t('admin.users.site_role')) ?></label>
                    <select id="role_id" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $e($role['id']) ?>"><?php echo $e($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label style="margin-top:1rem"><input type="checkbox" name="is_platform_admin" value="1" style="width:auto"> <?php echo $e($t('admin.users.platform_admin')) ?></label>
            <div class="admin-modal__actions">
                <button type="button" class="btn" data-user-create-cancel style="background:#78716c"><?php echo $e($t('admin.common.cancel')) ?></button>
                <button type="submit"><?php echo $e($t('admin.common.create')) ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="admin-modal" id="user-delete-modal" aria-labelledby="user-delete-title">
        <form method="post" action="<?php echo $e($basePath) ?>/admin/users/delete" id="user-delete-form">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <input type="hidden" name="user_id" id="user-delete-id" value="">
            <h2 id="user-delete-title"><?php echo $e($t('admin.users.delete_title')) ?></h2>
            <p class="muted"><?php echo $e($t('admin.users.delete_irreversible')) ?></p>
            <?php
            $deleteParts = explode('⟦LABEL⟧', $t('admin.users.delete_confirm', ['label' => '⟦LABEL⟧']), 2);
            ?>
            <p><?php echo $e($deleteParts[0] ?? '') ?><strong id="user-delete-label"></strong><?php echo $e($deleteParts[1] ?? '') ?></p>
            <div class="admin-modal__actions">
                <button type="button" class="btn" data-user-delete-cancel style="background:#78716c"><?php echo $e($t('admin.common.cancel')) ?></button>
                <button type="submit" class="btn-danger"><?php echo $e($t('admin.common.delete')) ?></button>
            </div>
        </form>
    </dialog>
    <script src="<?php echo $e($basePath) ?>/assets/admin/users.js" defer></script>
<?php else: ?>
    <p class="muted"><?php echo $e($t('admin.users.create_admin_only')) ?></p>
<?php endif; ?>
