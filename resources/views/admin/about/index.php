<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var string $productName */
/** @var string $productVersion */
/** @var string $tagline */
/** @var string $vendor */
/** @var string $vendorUrl */
/** @var string $copyright */
/** @var string $license */
/** @var string $licenseName */
/** @var string $licenseUri */
/** @var string $phpVersion */
/** @var string $sapi */
/** @var string $appEnv */
/** @var bool $appDebug */
/** @var string $appUrl */
/** @var string $dbDriver */
/** @var string $dbServer */
/** @var int $enabledPlugins */
/** @var string $themeKey */
/** @var string $os */
/** @var string $basePath */
/** @var string $csrf */
/** @var bool $marketplaceConfigured */
/** @var array{latest: string, phpRequirement: string, downloadUrl: string, updateAvailable: bool}|null $cmsUpdate */
/** @var bool $updateChecked */
/** @var string $updateCheckError */
/** @var bool $canUpgrade */
/** @var string $upgradeNotice */
/** @var string $upgradeError */
/** @var array<string, mixed>|null $upgradeProgress */
/** @var array<string, string> $upgradeStepLabels */
?>
<h1><?php echo $e($t('admin.about.title')) ?></h1>
<p class="muted"><?php echo $e($tagline) ?></p>

<?php
$upgradeNotice = (string) ($upgradeNotice ?? '');
$upgradeError = (string) ($upgradeError ?? '');
$canUpgrade = !empty($canUpgrade);
$upgradeProgress = is_array($upgradeProgress ?? null) ? $upgradeProgress : null;
$upgradeStepLabels = is_array($upgradeStepLabels ?? null) ? $upgradeStepLabels : [];
$upgradeStatus = is_array($upgradeProgress) ? (string) ($upgradeProgress['status'] ?? '') : '';
$upgradeBusy = in_array($upgradeStatus, ['queued', 'running'], true);
$showUpgradePanel = $upgradeBusy
    || (!empty($upgrading) && in_array($upgradeStatus, ['queued', 'running', 'done', 'failed', ''], true))
    || ($upgradeStatus === 'failed' && $upgradeError === '');
?>
<?php if ($upgradeNotice !== ''): ?>
    <p class="flash flash--success" role="status"><?php echo $e($upgradeNotice) ?></p>
<?php endif; ?>
<?php if ($upgradeError !== ''): ?>
    <p class="flash flash--error" role="alert"><?php echo $e($upgradeError) ?></p>
<?php endif; ?>

<?php if ($showUpgradePanel): ?>
    <?php
    $panelStatus = (string) ($upgradeProgress['status'] ?? 'queued');
    $panelSteps = [];
    foreach (($upgradeProgress['steps'] ?? []) as $step) {
        if (!is_array($step)) {
            continue;
        }
        $key = (string) ($step['key'] ?? '');
        $panelSteps[] = [
            'key' => $key,
            'state' => (string) ($step['state'] ?? 'pending'),
            'label' => (string) ($upgradeStepLabels[$key] ?? $key),
        ];
    }
    if ($panelSteps === []) {
        foreach (['maintenance', 'backup', 'download', 'extract', 'snapshot', 'apply', 'migrate', 'cache', 'finish'] as $key) {
            $panelSteps[] = [
                'key' => $key,
                'state' => 'pending',
                'label' => (string) ($upgradeStepLabels[$key] ?? $key),
            ];
        }
    }
    $initialPayload = [
        'id' => (string) ($upgradeProgress['id'] ?? ''),
        'status' => $panelStatus,
        'from' => (string) ($upgradeProgress['from'] ?? ''),
        'to' => (string) ($upgradeProgress['to'] ?? ''),
        'current' => (string) ($upgradeProgress['current'] ?? 'queued'),
        'currentLabel' => (string) ($upgradeStepLabels[(string) ($upgradeProgress['current'] ?? 'queued')]
            ?? $t('admin.about.upgrade_step.queued')),
        'steps' => $panelSteps,
        'message' => (string) ($upgradeProgress['message'] ?? ''),
        'backupStamp' => $upgradeProgress['backupStamp'] ?? null,
        'error' => $upgradeProgress['error'] ?? null,
        'spawned' => !empty($upgradeProgress['spawned']),
        'doneMessage' => '',
    ];
    ?>
    <div
        class="card cms-upgrade"
        data-cms-upgrade
        data-about-url="<?php echo $e($basePath) ?>/admin/about"
        data-status-url="<?php echo $e($basePath) ?>/admin/about/upgrade/status"
        data-run-url="<?php echo $e($basePath) ?>/admin/about/upgrade/run"
        data-csrf="<?php echo $e($csrf) ?>"
        data-initial="<?php echo $e(json_encode($initialPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?>"
        role="status"
        aria-live="polite"
    >
        <h2 style="margin-top:0"><?php echo $e($t('admin.about.upgrade_progress_title', [
            'from' => (string) ($upgradeProgress['from'] ?? ''),
            'to' => (string) ($upgradeProgress['to'] ?? ''),
        ])) ?></h2>
        <p class="muted" style="margin:0 0 .75rem"><?php echo $e($t('admin.about.upgrade_progress_hint')) ?></p>
        <p style="margin:0 0 .5rem"><strong data-cms-upgrade-status><?php echo $e((string) ($initialPayload['currentLabel'] ?? '')) ?></strong></p>
        <p class="muted" data-cms-upgrade-detail hidden style="margin:0 0 .75rem;font-size:.9rem"></p>
        <ol class="cms-upgrade__steps" data-cms-upgrade-steps style="list-style:none;margin:0;padding:0;display:grid;gap:.35rem">
            <?php foreach ($panelSteps as $step): ?>
                <li class="cms-upgrade__step cms-upgrade__step--<?php echo $e($step['state']) ?>" data-state="<?php echo $e($step['state']) ?>">
                    <span class="cms-upgrade__mark" aria-hidden="true"><?php
                        echo $step['state'] === 'done' ? '✓' : ($step['state'] === 'failed' ? '!' : ($step['state'] === 'active' ? '…' : '·'));
                    ?></span>
                    <span class="cms-upgrade__label"><?php echo $e($step['label']) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
        <p class="flash flash--error" data-cms-upgrade-error hidden role="alert" style="margin:.75rem 0 0"></p>
        <p class="flash flash--success" data-cms-upgrade-success hidden role="status" style="margin:.75rem 0 0"></p>
    </div>
    <style>
        .cms-upgrade__step { display:flex; gap:.55rem; align-items:flex-start; color:#78716c; }
        .cms-upgrade__step--active { color:#1c1917; font-weight:600; }
        .cms-upgrade__step--done { color:#166534; }
        .cms-upgrade__step--failed { color:#9f1239; font-weight:600; }
        .cms-upgrade__mark { width:1.1rem; text-align:center; flex:0 0 auto; }
        .cms-upgrade.is-failed { border-color:#fecdd3; }
        .cms-upgrade.is-done { border-color:#bbf7d0; }
    </style>
<?php endif; ?>

<div class="card">
    <div style="margin:0 0 1rem">
        <?php
        $variant = 'logo';
        $href = null;
        $imgClass = 'nexis-brand__img--logo about-logo';
        require dirname(__DIR__, 2) . '/partials/nexis-brand.php';
        ?>
    </div>
    <p class="about-version">
        <?php echo $e($t('admin.about.version')) ?> <strong><?php echo $e($productVersion) ?></strong>
    </p>
    <?php
    /** @var array{latest: string, phpRequirement: string, downloadUrl: string, updateAvailable: bool}|null $cmsUpdate */
    $cmsUpdate = is_array($cmsUpdate ?? null) ? $cmsUpdate : null;
    $marketplaceConfigured = !empty($marketplaceConfigured);
    $updateChecked = !empty($updateChecked);
    $updateCheckError = (string) ($updateCheckError ?? '');
    $updateAvailable = is_array($cmsUpdate) && !empty($cmsUpdate['updateAvailable']);
    ?>
    <?php if ($marketplaceConfigured): ?>
        <div class="about-update">
            <h3><?php echo $e($t('admin.about.updates')) ?></h3>
            <?php if ($updateCheckError !== ''): ?>
                <p class="flash flash--error" role="alert"><?php echo $e($updateCheckError) ?></p>
            <?php elseif ($updateChecked && $updateAvailable): ?>
                <p class="flash flash--info" role="status">
                    <?php echo $e($t('admin.about.update_available', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>
                    <?php if (($cmsUpdate['downloadUrl'] ?? '') !== ''): ?>
                        | <a href="<?php echo $e((string) $cmsUpdate['downloadUrl']) ?>" rel="noopener" target="_blank"><?php echo $e($t('admin.about.update_download')) ?></a>
                    <?php endif; ?>
                </p>
            <?php elseif ($updateChecked): ?>
                <p class="flash flash--success" role="status">
                    <?php echo $e($t('admin.about.update_current', [
                        'version' => (string) (is_array($cmsUpdate) ? ($cmsUpdate['latest'] ?? $productVersion) : $productVersion),
                    ])) ?>
                </p>
            <?php elseif ($updateAvailable): ?>
                <p class="flash flash--info" role="status">
                    <?php echo $e($t('admin.about.update_available', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>
                    <?php if (($cmsUpdate['downloadUrl'] ?? '') !== ''): ?>
                        | <a href="<?php echo $e((string) $cmsUpdate['downloadUrl']) ?>" rel="noopener" target="_blank"><?php echo $e($t('admin.about.update_download')) ?></a>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="muted" style="margin:0 0 .75rem"><?php echo $e($t('admin.about.update_hint')) ?></p>
            <?php endif; ?>
            <div
                style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center"
                data-marketplace-refresh="<?php echo !empty($marketplaceNeedsRefresh) ? '1' : '0' ?>"
                data-marketplace-refresh-url="<?php echo $e($basePath) ?>/admin/marketplace/refresh-updates"
                data-csrf="<?php echo $e($csrf) ?>"
            >
                <form method="post" action="<?php echo $e($basePath) ?>/admin/about/check-update" class="inline-form"
                      data-busy data-busy-label="<?php echo $e($t('admin.common.working')) ?>">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                    <button type="submit" class="btn btn-small"><?php echo $e($t('admin.about.update_check')) ?></button>
                </form>
                <span class="muted" data-marketplace-refresh-status hidden style="font-size:.85rem">
                    <?php echo $e($t('admin.plugins.marketplace.update_check_running')) ?>
                </span>
                <?php if ($updateAvailable && $canUpgrade): ?>
                    <?php if (!$upgradeBusy): ?>
                        <form method="post" action="<?php echo $e($basePath) ?>/admin/about/upgrade" class="inline-form"
                              data-confirm-title="<?php echo $e($t('admin.about.upgrade_run', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>"
                              data-confirm="<?php echo $e($t('admin.about.upgrade_confirm', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>"
                              data-confirm-ok="<?php echo $e($t('admin.about.upgrade_run', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>">
                            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                            <button type="submit" class="btn btn-small"><?php echo $e($t('admin.about.upgrade_run', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?></button>
                        </form>
                    <?php else: ?>
                        <span class="muted" style="font-size:.9rem"><?php echo $e($t('admin.about.upgrade_running')) ?></span>
                    <?php endif; ?>
                <?php elseif ($updateAvailable && !$canUpgrade): ?>
                    <p class="muted" style="margin:0"><?php echo $e($t('admin.about.upgrade_need_perm')) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($updateAvailable && $canUpgrade): ?>
                <p class="muted" style="margin:.75rem 0 0;font-size:.9rem"><?php echo $e($t('admin.about.upgrade_hint')) ?></p>
            <?php endif; ?>
            <?php
            /** @var list<array{version: string, phpRequirement: string, downloadUrl: string, changelogMd: string}> $cmsReleases */
            $cmsReleases = is_array($cmsReleases ?? null) ? $cmsReleases : [];
            ?>
            <?php if ($canUpgrade && $cmsReleases !== []): ?>
                <div style="margin-top:1rem;padding-top:.75rem;border-top:1px solid var(--admin-border, #e7e5e4)">
                    <h4 style="margin:0 0 .5rem;font-size:1rem"><?php echo $e($t('admin.about.downgrade_title')) ?></h4>
                    <p class="muted" style="margin:0 0 .75rem;font-size:.9rem"><?php echo $e($t('admin.about.downgrade_hint')) ?></p>
                    <ul style="list-style:none;margin:0;padding:0;display:grid;gap:.5rem">
                        <?php foreach ($cmsReleases as $release): ?>
                            <li style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
                                <span><strong>v<?php echo $e((string) ($release['version'] ?? '')) ?></strong></span>
                                <form method="post" action="<?php echo $e($basePath) ?>/admin/about/upgrade" class="inline-form"
                                      data-confirm-title="<?php echo $e($t('admin.about.downgrade_run', ['version' => (string) ($release['version'] ?? '')])) ?>"
                                      data-confirm="<?php echo $e($t('admin.about.downgrade_confirm', ['version' => (string) ($release['version'] ?? '')])) ?>"
                                      data-confirm-ok="<?php echo $e($t('admin.about.downgrade_run', ['version' => (string) ($release['version'] ?? '')])) ?>">
                                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                                    <input type="hidden" name="version" value="<?php echo $e((string) ($release['version'] ?? '')) ?>">
                                    <button type="submit" class="btn btn-small"<?php echo $upgradeBusy ? ' disabled' : '' ?>><?php echo $e($t('admin.about.downgrade_run', ['version' => (string) ($release['version'] ?? '')])) ?></button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <dl class="about-meta">
        <dt><?php echo $e($t('admin.about.copyright')) ?></dt>
        <dd><?php echo $e($copyright) ?></dd>
        <dt><?php echo $e($t('admin.about.vendor')) ?></dt>
        <dd>
            <?php if ($vendorUrl !== ''): ?>
                <a href="<?php echo $e($vendorUrl) ?>" target="_blank" rel="noopener"><?php echo $e($vendor) ?></a>
            <?php else: ?>
                <?php echo $e($vendor) ?>
            <?php endif; ?>
        </dd>
        <dt><?php echo $e($t('admin.about.license')) ?></dt>
        <dd>
            <a href="<?php echo $e($licenseUri) ?>" target="_blank" rel="noopener"><?php echo $e($licenseName) ?></a>
            (<?php echo $e($license) ?>)
            | <a href="<?php echo $e($basePath) ?>/admin/about/license"><?php echo $e($t('admin.about.license_full')) ?></a>
        </dd>
        <dt><?php echo $e($t('admin.about.website')) ?></dt>
        <dd><?php echo $e($site->name) ?> | <?php echo $e($site->primaryDomain) ?></dd>
        <dt><?php echo $e($t('admin.about.environment')) ?></dt>
        <dd>
            <?php echo $e($appEnv) ?>
            | Debug <?php echo $appDebug ? $e($t('admin.about.debug_on')) : $e($t('admin.about.debug_off')) ?>
            <?php if ($appUrl !== ''): ?>
                | <a href="<?php echo $e($appUrl) ?>" target="_blank" rel="noopener"><?php echo $e($appUrl) ?></a>
            <?php endif; ?>
        </dd>
        <dt><?php echo $e($t('admin.about.theme')) ?></dt>
        <dd><?php echo $themeKey !== '' ? $e($themeKey) : $e($t('admin.common.empty_dash')) ?></dd>
        <dt><?php echo $e($t('admin.about.plugins_active')) ?></dt>
        <dd><?php echo (int) $enabledPlugins ?></dd>
    </dl>
    <p class="muted about-disclaimer">
        <?php echo $e($t('admin.about.disclaimer')) ?>
    </p>
</div>

<div class="card">
    <h2><?php echo $e($t('admin.about.runtime')) ?></h2>
    <dl class="about-meta">
        <dt>PHP</dt>
        <dd><?php echo $e($phpVersion) ?> (<?php echo $e($sapi) ?>)</dd>
        <dt><?php echo $e($t('admin.about.database')) ?></dt>
        <dd><?php echo $e($dbDriver) ?> | <?php echo $e($dbServer) ?></dd>
        <dt><?php echo $e($t('admin.about.system')) ?></dt>
        <dd><?php echo $e($os) ?></dd>
    </dl>
    <p class="muted" style="margin-top:1rem">
        <?php echo $e($t('admin.about.third_party')) ?>
    </p>
</div>
