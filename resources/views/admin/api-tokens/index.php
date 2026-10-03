<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($t('admin.api_tokens.title')) ?></h1>
<?php if ($notice !== ''): ?><p class="flash flash--success" role="status"><?php echo $e($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="flash flash--error" role="alert"><?php echo $e($error) ?></p><?php endif; ?>
<p class="muted"><?php echo $e($t('admin.api_tokens.intro')) ?></p>

<?php if ($plaintext !== ''): ?>
    <div class="card">
        <h2><?php echo $e($t('admin.api_tokens.secret_heading')) ?></h2>
        <p class="flash flash--info"><?php echo $e($t('admin.api_tokens.secret_once')) ?></p>
        <p><input type="text" value="<?php echo $e($plaintext) ?>" readonly onclick="this.select()"></p>
        <p class="muted"><code>Authorization: Bearer <?php echo $e($plaintext) ?></code></p>
    </div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $e($t('admin.api_tokens.new')) ?></h2>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/api-tokens">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <label for="name"><?php echo $e($t('admin.api_tokens.name')) ?></label>
        <input id="name" name="name" type="text" maxlength="120" required placeholder="CI Deploy">
        <label><?php echo $e($t('admin.api_tokens.scopes')) ?></label>
        <p class="muted"><?php echo $e($t('admin.api_tokens.scopes_hint')) ?></p>
        <?php foreach ($scopeOptions as $permission): ?>
            <?php $field = 'scope_' . str_replace('.', '_', (string) $permission); ?>
            <label class="check-label">
                <input type="checkbox" name="<?php echo $e($field) ?>" value="1">
                <code><?php echo $e((string) $permission) ?></code>
            </label>
        <?php endforeach; ?>
        <p><button type="submit"><?php echo $e($t('admin.common.create')) ?></button></p>
    </form>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.api_tokens.list')) ?></h2>
    <?php if ($tokens === []): ?>
        <p class="muted"><?php echo $e($t('admin.api_tokens.empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th><?php echo $e($t('admin.api_tokens.name')) ?></th>
                <th><?php echo $e($t('admin.api_tokens.prefix')) ?></th>
                <th><?php echo $e($t('admin.api_tokens.scopes')) ?></th>
                <th><?php echo $e($t('admin.api_tokens.last_used')) ?></th>
                <th><?php echo $e($t('admin.common.status')) ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tokens as $token): ?>
                <tr>
                    <td><?php echo $e($token->name) ?></td>
                    <td><code><?php echo $e($token->tokenPrefix) ?>…</code></td>
                    <td class="muted">
                        <?php echo $token->scopes === []
                            ? $e($t('admin.api_tokens.scopes_all'))
                            : $e(implode(', ', $token->scopes)) ?>
                    </td>
                    <td class="muted">
                        <?php echo $token->lastUsedAt !== null
                            ? $e($token->lastUsedAt->format('Y-m-d H:i'))
                            : $e($t('admin.common.empty_dash')) ?>
                    </td>
                    <td>
                        <?php if ($token->isRevoked()): ?>
                            <span class="badge"><?php echo $e($t('admin.api_tokens.status_revoked')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-admin"><?php echo $e($t('admin.api_tokens.status_active')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$token->isRevoked()): ?>
                            <form method="post" action="<?php echo $e($basePath) ?>/admin/api-tokens/revoke">
                                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?php echo $e($token->id) ?>">
                                <button type="submit" class="btn-danger btn-small"><?php echo $e($t('admin.api_tokens.revoke')) ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
