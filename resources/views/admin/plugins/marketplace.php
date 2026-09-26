<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var array{items: list<array<string, mixed>>, total: int, page: int, perPage: int} $result */
/** @var array<string, string> $installed */
$items = $result['items'] ?? [];
$total = (int) ($result['total'] ?? 0);
$page = max(1, (int) ($result['page'] ?? 1));
$perPage = max(1, (int) ($result['perPage'] ?? 20));
$pages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
?>
<p class="muted"><a href="<?php echo $e($basePath) ?>/admin/plugins"><?php echo $e($t('admin.plugins.title')) ?></a></p>
<h1><?php echo $e($t('admin.plugins.marketplace.title')) ?></h1>
<p class="muted"><?php echo $e($t('admin.plugins.marketplace.intro')) ?></p>

<?php if (($marketplaceError ?? '') !== ''): ?>
    <p class="flash flash--error" role="alert"><?php echo $e($marketplaceError) ?></p>
<?php endif; ?>

<?php if (empty($configured)): ?>
    <div class="card">
        <p style="margin:0"><?php echo $e($t('admin.plugins.marketplace.not_configured')) ?></p>
    </div>
<?php else: ?>
    <?php if (($marketplaceUrl ?? '') !== ''): ?>
        <p class="muted">
            <a href="<?php echo $e((string) $marketplaceUrl) ?>/plugins" target="_blank" rel="noopener"><?php echo $e($t('admin.plugins.marketplace.open_directory')) ?></a>
        </p>
    <?php endif; ?>
    <div class="card">
        <form method="get" action="<?php echo $e($basePath) ?>/admin/plugins/marketplace" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:end">
            <p style="margin:0;flex:1;min-width:12rem">
                <label for="q"><?php echo $e($t('admin.plugins.marketplace.search')) ?></label>
                <input id="q" name="q" type="search" value="<?php echo $e((string) ($q ?? '')) ?>" placeholder="<?php echo $e($t('admin.plugins.marketplace.search_placeholder')) ?>">
            </p>
            <p style="margin:0"><button type="submit"><?php echo $e($t('admin.common.search')) ?></button></p>
        </form>
    </div>

    <p class="muted"><?php echo $e($t('admin.plugins.marketplace.found', ['count' => (string) $total])) ?></p>

    <?php if ($items === []): ?>
        <div class="card"><p class="muted" style="margin:0"><?php echo $e($t('admin.plugins.marketplace.empty')) ?></p></div>
    <?php else: ?>
        <div class="card" style="padding:0;overflow:auto">
            <table>
                <thead>
                    <tr>
                        <th><?php echo $e($t('admin.plugins.plugin')) ?></th>
                        <th><?php echo $e($t('admin.plugins.author')) ?></th>
                        <th><?php echo $e($t('admin.plugins.version')) ?></th>
                        <th><?php echo $e($t('admin.plugins.compatible_core')) ?></th>
                        <th><?php echo $e($t('admin.common.status')) ?></th>
                        <th><?php echo $e($t('admin.plugins.action')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        if (!is_array($item)) {
                            continue;
                        }
                        $slug = (string) ($item['slug'] ?? '');
                        $local = $installed[$slug] ?? null;
                        $localVersion = (string) (($installedVersions[$slug] ?? '') ?: '');
                        $remoteVersion = (string) ($item['version'] ?? '');
                        $hasUpdate = $local !== null
                            && $localVersion !== ''
                            && $remoteVersion !== ''
                            && version_compare(ltrim($remoteVersion, 'vV'), ltrim($localVersion, 'vV'), '>');
                        $statusLabel = $local === null
                            ? $t('admin.plugins.marketplace.status.available')
                            : ($hasUpdate
                                ? $t('admin.plugins.marketplace.status.update')
                                : ($local === 'enabled'
                                    ? $t('admin.plugins.status.enabled')
                                    : $t('admin.plugins.marketplace.status.installed')));
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo $e((string) ($item['name'] ?? $slug)) ?></strong>
                                <div><code><?php echo $e($slug) ?></code></div>
                                <?php if (($item['summary'] ?? '') !== ''): ?>
                                    <p class="muted" style="margin:.35rem 0 0;font-size:.9rem"><?php echo $e((string) $item['summary']) ?></p>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $e((string) ($item['author'] ?? '')) ?></td>
                            <td>
                                <code><?php echo $e($remoteVersion) ?></code>
                                <?php if ($local !== null && $localVersion !== ''): ?>
                                    <div class="muted" style="font-size:.85rem"><?php echo $e($t('admin.plugins.marketplace.local_version', ['version' => $localVersion])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo $e((string) ($item['compatibleCore'] ?? '')) ?></code></td>
                            <td>
                                <?php if ($hasUpdate): ?>
                                    <span class="badge" style="background:#fef3c7;color:#92400e"><?php echo $e($statusLabel) ?></span>
                                <?php elseif ($local === 'enabled'): ?>
                                    <span class="badge badge-admin"><?php echo $e($statusLabel) ?></span>
                                <?php else: ?>
                                    <span class="badge"><?php echo $e($statusLabel) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasUpdate && !empty($canInstall) && ($item['downloadUrl'] ?? null) !== null): ?>
                                    <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/marketplace/install" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                        <input type="hidden" name="plugin" value="<?php echo $e($slug) ?>">
                                        <input type="hidden" name="overwrite" value="1">
                                        <button type="submit" class="btn-small"><?php echo $e($t('admin.plugins.marketplace.update')) ?></button>
                                    </form>
                                <?php elseif ($local !== null): ?>
                                    <span class="muted"><?php echo $e($t('admin.plugins.marketplace.already_local')) ?></span>
                                <?php elseif (!empty($canInstall) && ($item['downloadUrl'] ?? null) !== null && ($item['version'] ?? '') !== ''): ?>
                                    <form method="post" action="<?php echo $e($basePath) ?>/admin/plugins/marketplace/install" style="margin:0">
                                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                        <input type="hidden" name="plugin" value="<?php echo $e($slug) ?>">
                                        <button type="submit" class="btn-small"><?php echo $e($t('admin.plugins.marketplace.install')) ?></button>
                                    </form>
                                <?php elseif (!empty($canInstall)): ?>
                                    <span class="muted"><?php echo $e($t('admin.plugins.marketplace.no_package')) ?></span>
                                <?php else: ?>
                                    <span class="muted"><?php echo $e($t('admin.plugins.marketplace.no_install_perm')) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <p class="muted" style="margin-top:1rem">
                <?php if ($page > 1): ?>
                    <a href="<?php echo $e($basePath) ?>/admin/plugins/marketplace?q=<?php echo $e(rawurlencode((string) ($q ?? ''))) ?>&page=<?php echo $e((string) ($page - 1)) ?>"><?php echo $e($t('admin.common.prev')) ?></a>
                <?php endif; ?>
                <?php echo $e($t('admin.plugins.marketplace.page', ['page' => (string) $page, 'pages' => (string) $pages])) ?>
                <?php if ($page < $pages): ?>
                    <a href="<?php echo $e($basePath) ?>/admin/plugins/marketplace?q=<?php echo $e(rawurlencode((string) ($q ?? ''))) ?>&page=<?php echo $e((string) ($page + 1)) ?>"><?php echo $e($t('admin.common.next')) ?></a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
