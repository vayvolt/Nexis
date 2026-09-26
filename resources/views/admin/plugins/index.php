<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var list<array{
 *   key: string,
 *   name: string,
 *   version: string,
 *   author: string,
 *   status: string,
 *   license?: string,
 *   licenseUri?: string,
 *   compatibleCore?: string,
 *   php?: string,
 *   blocks?: list<string>,
 *   permissions?: list<string>,
 *   slots?: list<string>
 * }> $plugins */
$statusLabels = [
    'enabled' => $t('admin.plugins.status.enabled'),
    'disabled' => $t('admin.plugins.status.disabled'),
    'installed' => $t('admin.plugins.status.installed'),
    'discovered' => $t('admin.plugins.status.discovered'),
];
$adminPaths = [
    'nexis/forms' => '/admin/forms',
    'nexis/redirects' => '/admin/redirects',
    'nexis/consent' => '/admin/consent',
    'nexis/blog' => '/admin/blog',
    'nexis/catalog' => '/admin/catalog',
    'nexis/workshop' => '/admin/workshop',
];
$pluginMeta = static function (string $key) use ($t, $adminPaths): array {
    $short = str_contains($key, '/') ? substr($key, (int) strrpos($key, '/') + 1) : $key;
    $navKey = 'admin.nav.' . $short;
    $descKey = 'admin.plugins.desc.' . $short;
    $label = $t($navKey, [], '');
    $desc = $t($descKey, [], '');

    return [
        'short' => $short,
        'label' => $label !== '' && $label !== $navKey ? $label : null,
        'description' => $desc !== '' && $desc !== $descKey ? $desc : null,
        'path' => $adminPaths[$key] ?? null,
    ];
};
$joinCodes = static function (array $items): string {
    $clean = [];
    foreach ($items as $item) {
        if (is_string($item) && $item !== '') {
            $clean[] = $item;
        }
    }

    return implode(', ', $clean);
};
$enabledCount = 0;
foreach ($plugins as $plugin) {
    if ($plugin['status'] === 'enabled') {
        $enabledCount++;
    }
}
?>
<style>
    .plugin-details { margin: 0; }
    .plugin-details > summary {
        list-style: none;
        cursor: pointer;
        display: inline-flex;
        align-items: baseline;
        gap: .4rem;
        color: inherit;
    }
    .plugin-details > summary::-webkit-details-marker { display: none; }
    .plugin-details > summary::after {
        content: "▸";
        font-size: .75rem;
        color: var(--muted);
        transition: transform .15s ease;
    }
    .plugin-details[open] > summary::after { transform: rotate(90deg); }
    .plugin-details > summary:hover strong { text-decoration: underline; }
    .plugin-details__body {
        margin: .55rem 0 0;
        padding: .75rem .85rem;
        border: 1px solid #e7e5e4;
        border-radius: 6px;
        background: #fafaf9;
        max-width: 36rem;
    }
    .plugin-details__body p { margin: 0 0 .65rem; font-size: .9rem; }
    .plugin-details__body dl { margin: 0; }
    .plugin-details__body .about-meta { grid-template-columns: 8.5rem 1fr; gap: .35rem .75rem; margin: 0; font-size: .9rem; }
    .plugin-details__body .about-meta code { font-size: .85em; word-break: break-word; }
    .plugin-warnings { margin: .4rem 0 0; display: flex; flex-direction: column; gap: .3rem; align-items: flex-start; }
    .plugin-warnings .badge-warn { background: #fef3c7; color: #92400e; }
    .plugin-details__body .is-warn { color: #92400e; font-weight: 600; }
</style>
<h1><?php echo $e($t('admin.plugins.title')) ?></h1>
<p class="muted">
    <?php echo $e($t('admin.plugins.intro')) ?>
    · <?php echo $e($t('admin.plugins.found', ['count' => (string) count($plugins)])) ?>
    · <span class="badge badge-admin"><?php echo $e($t('admin.plugins.active', ['count' => (string) (int) $enabledCount])) ?></span>
</p>
<?php
$flashClass = match ($noticeTone ?? 'success') {
    'info' => 'flash--info',
    'error' => 'flash--error',
    default => 'flash--success',
};
?>
<?php if (($notice ?? '') !== ''): ?>
    <p class="flash <?php echo $e($flashClass) ?>" role="status"><?php echo $e($notice) ?></p>
<?php endif; ?>
<?php if (($error ?? '') !== ''): ?>
    <p class="flash flash--error" role="alert"><?php echo $e($error) ?></p>
<?php endif; ?>

<p class="muted"><?php echo $e($t('admin.plugins.boot_hint')) ?></p>
<p><a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/plugins/marketplace"><?php echo $e($t('admin.plugins.marketplace.link')) ?></a></p>
<?php
/** @var array<string, array{installed: string, latest: string, downloadUrl: string|null}> $marketplaceUpdates */
$marketplaceUpdates = is_array($marketplaceUpdates ?? null) ? $marketplaceUpdates : [];
/** @var array{latest: string, phpRequirement: string, downloadUrl: string, updateAvailable: bool}|null $cmsUpdate */
$cmsUpdate = is_array($cmsUpdate ?? null) ? $cmsUpdate : null;
?>
<?php if (is_array($cmsUpdate) && !empty($cmsUpdate['updateAvailable'])): ?>
    <p class="flash flash--info" role="status">
        <?php echo $e($t('admin.plugins.marketplace.cms_update_available', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>
        <?php if (($cmsUpdate['downloadUrl'] ?? '') !== ''): ?>
            · <a href="<?php echo $e((string) $cmsUpdate['downloadUrl']) ?>" rel="noopener" target="_blank"><?php echo $e($t('admin.plugins.marketplace.cms_download')) ?></a>
        <?php endif; ?>
    </p>
<?php endif; ?>
<?php if ($marketplaceUpdates !== []): ?>
    <p class="flash flash--info" role="status">
        <?php echo $e($t('admin.plugins.marketplace.updates_available', ['count' => (string) count($marketplaceUpdates)])) ?>
        · <a href="<?php echo $e($basePath) ?>/admin/plugins/marketplace"><?php echo $e($t('admin.plugins.marketplace.link')) ?></a>
    </p>
<?php endif; ?>
<?php if (!empty($marketplaceConfigured)): ?>
    <p class="muted" style="font-size:.9rem"><?php echo $e($t('admin.plugins.marketplace.update_check_hint')) ?></p>
<?php endif; ?>

<?php if (!empty($canInstall)): ?>
    <div class="card">
        <h2><?php echo $e($t('admin.plugins.install_zip')) ?></h2>
        <p class="muted"><?php echo $e($t('admin.plugins.install_hint')) ?></p>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/upload" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <label for="package"><?php echo $e($t('admin.plugins.zip_file')) ?></label>
            <input id="package" type="file" name="package" accept=".zip,application/zip" required>
            <label class="check-label"><input type="checkbox" name="overwrite" value="1"> <?php echo $e($t('admin.plugins.overwrite')) ?></label>
            <p><button type="submit"><?php echo $e($t('admin.plugins.upload_install')) ?></button></p>
        </form>
    </div>
<?php endif; ?>

<?php if ($plugins === []): ?>
    <div class="card">
        <p class="muted" style="margin:0"><?php echo $e($t('admin.plugins.empty')) ?></p>
    </div>
<?php else: ?>
    <div class="card" style="padding:0;overflow:auto">
        <table>
            <thead>
                <tr>
                    <th><?php echo $e($t('admin.plugins.plugin')) ?></th>
                    <th><?php echo $e($t('admin.plugins.author')) ?></th>
                    <th><?php echo $e($t('admin.plugins.version')) ?></th>
                    <th><?php echo $e($t('admin.common.status')) ?></th>
                    <th style="width:12rem"><?php echo $e($t('admin.plugins.action')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $plugin): ?>
                    <?php
                    $status = $plugin['status'];
                    $isEnabled = $status === 'enabled';
                    $label = $statusLabels[$status] ?? $status;
                    $meta = $pluginMeta($plugin['key']);
                    $desc = (string) ($meta['description'] ?? '');
                    $author = trim((string) ($plugin['author'] ?? ''));
                    $license = trim((string) ($plugin['license'] ?? ''));
                    $licenseUri = trim((string) ($plugin['licenseUri'] ?? ''));
                    $compatibleCore = trim((string) ($plugin['compatibleCore'] ?? ''));
                    $phpReq = trim((string) ($plugin['php'] ?? ''));
                    $blocks = $joinCodes($plugin['blocks'] ?? []);
                    $permissions = $joinCodes($plugin['permissions'] ?? []);
                    $slots = $joinCodes($plugin['slots'] ?? []);
                    $adminPath = ($isEnabled && is_string($meta['path']) && $meta['path'] !== '')
                        ? (string) $meta['path']
                        : '';
                    $phpOk = $phpReq === '' || \Nexis\Plugin\SemVer::satisfies(PHP_VERSION, $phpReq);
                    $licenseMissing = $license === '';
                    $hasExtra = $desc !== ''
                        || $license !== ''
                        || $compatibleCore !== ''
                        || $phpReq !== ''
                        || $blocks !== ''
                        || $permissions !== ''
                        || $slots !== ''
                        || !$phpOk
                        || $licenseMissing;
                    ?>
                    <tr>
                        <td>
                            <?php if ($hasExtra): ?>
                                <details class="plugin-details">
                                    <summary>
                                        <strong><?php echo $e($plugin['name']) ?></strong>
                                    </summary>
                                    <div class="plugin-details__body">
                                        <?php if ($desc !== ''): ?>
                                            <p class="muted"><?php echo $e($desc) ?></p>
                                        <?php endif; ?>
                                        <dl class="about-meta">
                                            <dt><?php echo $e($t('admin.plugins.id')) ?></dt>
                                            <dd><code><?php echo $e($plugin['key']) ?></code></dd>
                                            <?php if ($compatibleCore !== ''): ?>
                                                <dt><?php echo $e($t('admin.plugins.compatible_core')) ?></dt>
                                                <dd><code><?php echo $e($compatibleCore) ?></code></dd>
                                            <?php endif; ?>
                                            <dt><?php echo $e($t('admin.plugins.php')) ?></dt>
                                            <dd<?php echo !$phpOk ? ' class="is-warn"' : '' ?>>
                                                <?php if ($phpReq !== ''): ?>
                                                    <code><?php echo $e($phpReq) ?></code>
                                                    · PHP <?php echo $e(PHP_VERSION) ?>
                                                    <?php if (!$phpOk): ?>
                                                        · <?php echo $e($t('admin.plugins.warn.php_short')) ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="muted"><?php echo $e($t('admin.common.empty_dash')) ?></span>
                                                <?php endif; ?>
                                            </dd>
                                            <dt><?php echo $e($t('admin.plugins.license')) ?></dt>
                                            <dd<?php echo $licenseMissing ? ' class="is-warn"' : '' ?>>
                                                <?php if (!$licenseMissing): ?>
                                                    <?php if ($licenseUri !== ''): ?>
                                                        <a href="<?php echo $e($licenseUri) ?>" target="_blank" rel="noopener"><?php echo $e($license) ?></a>
                                                    <?php else: ?>
                                                        <?php echo $e($license) ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php echo $e($t('admin.plugins.warn.license')) ?>
                                                <?php endif; ?>
                                            </dd>
                                            <?php if ($blocks !== ''): ?>
                                                <dt><?php echo $e($t('admin.plugins.blocks')) ?></dt>
                                                <dd><code><?php echo $e($blocks) ?></code></dd>
                                            <?php endif; ?>
                                            <?php if ($permissions !== ''): ?>
                                                <dt><?php echo $e($t('admin.plugins.permissions')) ?></dt>
                                                <dd><code><?php echo $e($permissions) ?></code></dd>
                                            <?php endif; ?>
                                            <?php if ($slots !== ''): ?>
                                                <dt><?php echo $e($t('admin.plugins.slots')) ?></dt>
                                                <dd><code><?php echo $e($slots) ?></code></dd>
                                            <?php endif; ?>
                                        </dl>
                                    </div>
                                </details>
                            <?php else: ?>
                                <strong><?php echo $e($plugin['name']) ?></strong>
                            <?php endif; ?>
                            <div class="muted" style="margin-top:.25rem;font-size:.85rem"><code><?php echo $e($plugin['key']) ?></code></div>
                            <?php if (!$phpOk || $licenseMissing): ?>
                                <div class="plugin-warnings" role="status">
                                    <?php if (!$phpOk): ?>
                                        <span class="badge badge-warn" title="<?php echo $e($t('admin.plugins.warn.php', [
                                            'current' => PHP_VERSION,
                                            'required' => $phpReq !== '' ? $phpReq : '—',
                                        ])) ?>">
                                            <?php echo $e($t('admin.plugins.warn.php_short')) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($licenseMissing): ?>
                                        <span class="badge badge-warn"><?php echo $e($t('admin.plugins.warn.license')) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($adminPath !== ''): ?>
                                <p style="margin:.4rem 0 0">
                                    <a class="btn btn-small" href="<?php echo $e($basePath . $adminPath) ?>"><?php echo $e((string) ($meta['label'] ?? $plugin['name'])) ?></a>
                                </p>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($author !== ''): ?>
                                <?php echo $e($author) ?>
                            <?php else: ?>
                                <span class="muted"><?php echo $e($t('admin.common.empty_dash')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo $e($plugin['version']) ?></code>
                            <?php
                            $upd = $marketplaceUpdates[$plugin['key'] ?? ''] ?? null;
                            if (is_array($upd)):
                            ?>
                                <div class="plugin-warnings">
                                    <span class="badge badge-warn">
                                        <?php echo $e($t('admin.plugins.marketplace.update_to', ['version' => (string) ($upd['latest'] ?? '')])) ?>
                                    </span>
                                </div>
                                <?php if (!empty($canInstall) && ($upd['downloadUrl'] ?? null) !== null): ?>
                                    <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/marketplace/install" style="margin:.35rem 0 0">
                                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                        <input type="hidden" name="plugin" value="<?php echo $e((string) $plugin['key']) ?>">
                                        <input type="hidden" name="overwrite" value="1">
                                        <button type="submit" class="btn-small"><?php echo $e($t('admin.plugins.marketplace.update')) ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isEnabled): ?>
                                <span class="badge badge-admin"><?php echo $e($label) ?></span>
                            <?php else: ?>
                                <span class="badge"><?php echo $e($label) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isEnabled): ?>
                                <?php if (!empty(($pluginsWithSettings ?? [])[$plugin['key'] ?? ''])): ?>
                                    <p style="margin:0 0 .35rem">
                                        <a class="btn btn-small btn-muted" href="<?php echo $e($basePath) ?>/admin/plugins/settings?plugin=<?php echo $e(rawurlencode((string) $plugin['key'])) ?>"><?php echo $e($t('admin.plugins.settings.link')) ?></a>
                                    </p>
                                <?php endif; ?>
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/disable" style="margin-bottom:.35rem">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="plugin" value="<?php echo $e($plugin['key']) ?>">
                                    <button type="submit" class="btn-small" style="background:#78716c"><?php echo $e($t('admin.common.disable')) ?></button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/enable" style="margin-bottom:.35rem">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="plugin" value="<?php echo $e($plugin['key']) ?>">
                                    <button type="submit" class="btn-small"><?php echo $e($t('admin.common.enable')) ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($status !== 'discovered'): ?>
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/uninstall"
                                      onsubmit="return confirm(<?php echo json_encode($t('admin.plugins.uninstall_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>);">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="plugin" value="<?php echo $e($plugin['key']) ?>">
                                    <input type="hidden" name="confirm" value="1">
                                    <button type="submit" class="btn-small" style="background:#b91c1c"><?php echo $e($t('admin.plugins.uninstall')) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
