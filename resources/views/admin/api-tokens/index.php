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
        <div class="form-grid">
            <div class="form-grid__full">
                <input type="text" value="<?php echo $e($plaintext) ?>" readonly onclick="this.select()" aria-label="<?php echo $e($t('admin.api_tokens.secret_heading')) ?>">
            </div>
        </div>
        <p class="muted"><code>Authorization: Bearer <?php echo $e($plaintext) ?></code></p>
    </div>
<?php endif; ?>

<div class="card">
    <h2><?php echo $e($t('admin.api_tokens.new')) ?></h2>
    <form method="post" action="<?php echo $e($basePath) ?>/admin/api-tokens">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <div class="form-grid">
            <div>
                <label for="name"><?php echo $e($t('admin.api_tokens.name')) ?></label>
                <input id="name" name="name" type="text" maxlength="120" required placeholder="CI Deploy">
            </div>
        </div>
        <div class="form-section">
            <strong><?php echo $e($t('admin.api_tokens.scopes')) ?></strong>
            <p class="form-hint"><?php echo $e($t('admin.api_tokens.scopes_hint')) ?></p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:2.5rem"></th>
                            <th><?php echo $e($t('admin.api_tokens.scope_label')) ?></th>
                            <th><?php echo $e($t('admin.api_tokens.scope_key')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scopeOptions as $permission): ?>
                            <?php
                            $permission = (string) $permission;
                            $field = 'scope_' . str_replace('.', '_', $permission);
                            $label = $t('admin.permission.' . $permission, [], $permission);
                            $inputId = 'api_scope_' . $field;
                            ?>
                            <tr>
                                <td style="text-align:center">
                                    <input id="<?php echo $e($inputId) ?>" type="checkbox" name="<?php echo $e($field) ?>" value="1" aria-label="<?php echo $e($label) ?>">
                                </td>
                                <td><label for="<?php echo $e($inputId) ?>"><?php echo $e($label) ?></label></td>
                                <td style="white-space:nowrap"><code><?php echo $e($permission) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p><button type="submit"><?php echo $e($t('admin.common.create')) ?></button></p>
    </form>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.api_tokens.list')) ?></h2>
    <?php if ($tokens === []): ?>
        <p class="muted"><?php echo $e($t('admin.api_tokens.empty')) ?></p>
    <?php else: ?>
        <div class="table-wrap" style="margin:0">
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
                                ? $e(\Nexis\Support\AdminDateTime::format($token->lastUsedAt, (string) ($user->uiLocale ?? 'de')))
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
        </div>
    <?php endif; ?>
</div>
