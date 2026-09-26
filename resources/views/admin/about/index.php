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
?>
<h1><?php echo $e($t('admin.about.title')) ?></h1>
<p class="muted"><?php echo $e($tagline) ?></p>

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
    ?>
    <?php if ($marketplaceConfigured): ?>
        <div class="about-update">
            <h3><?php echo $e($t('admin.about.updates')) ?></h3>
            <?php if ($updateCheckError !== ''): ?>
                <p class="flash flash--error" role="alert"><?php echo $e($updateCheckError) ?></p>
            <?php elseif ($updateChecked && is_array($cmsUpdate) && !empty($cmsUpdate['updateAvailable'])): ?>
                <p class="flash flash--info" role="status">
                    <?php echo $e($t('admin.about.update_available', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>
                    <?php if (($cmsUpdate['downloadUrl'] ?? '') !== ''): ?>
                        · <a href="<?php echo $e((string) $cmsUpdate['downloadUrl']) ?>" rel="noopener" target="_blank"><?php echo $e($t('admin.about.update_download')) ?></a>
                    <?php endif; ?>
                </p>
            <?php elseif ($updateChecked): ?>
                <p class="flash flash--success" role="status">
                    <?php echo $e($t('admin.about.update_current', [
                        'version' => (string) (is_array($cmsUpdate) ? ($cmsUpdate['latest'] ?? $productVersion) : $productVersion),
                    ])) ?>
                </p>
            <?php elseif (is_array($cmsUpdate) && !empty($cmsUpdate['updateAvailable'])): ?>
                <p class="flash flash--info" role="status">
                    <?php echo $e($t('admin.about.update_available', ['version' => (string) ($cmsUpdate['latest'] ?? '')])) ?>
                    <?php if (($cmsUpdate['downloadUrl'] ?? '') !== ''): ?>
                        · <a href="<?php echo $e((string) $cmsUpdate['downloadUrl']) ?>" rel="noopener" target="_blank"><?php echo $e($t('admin.about.update_download')) ?></a>
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="muted" style="margin:0 0 .75rem"><?php echo $e($t('admin.about.update_hint')) ?></p>
            <?php endif; ?>
            <form method="post" action="<?php echo $e($basePath) ?>/admin/about/check-update" class="inline-form">
                <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                <button type="submit" class="btn btn-small"><?php echo $e($t('admin.about.update_check')) ?></button>
            </form>
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
            · <a href="<?php echo $e($basePath) ?>/admin/about/license"><?php echo $e($t('admin.about.license_full')) ?></a>
        </dd>
        <dt><?php echo $e($t('admin.about.website')) ?></dt>
        <dd><?php echo $e($site->name) ?> · <?php echo $e($site->primaryDomain) ?></dd>
        <dt><?php echo $e($t('admin.about.environment')) ?></dt>
        <dd>
            <?php echo $e($appEnv) ?>
            · Debug <?php echo $appDebug ? $e($t('admin.about.debug_on')) : $e($t('admin.about.debug_off')) ?>
            <?php if ($appUrl !== ''): ?>
                · <a href="<?php echo $e($appUrl) ?>" target="_blank" rel="noopener"><?php echo $e($appUrl) ?></a>
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
        <dd><?php echo $e($dbDriver) ?> · <?php echo $e($dbServer) ?></dd>
        <dt><?php echo $e($t('admin.about.system')) ?></dt>
        <dd><?php echo $e($os) ?></dd>
    </dl>
    <p class="muted" style="margin-top:1rem">
        <?php echo $e($t('admin.about.third_party')) ?>
    </p>
</div>
