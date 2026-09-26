<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
/** @var string $copyright */
/** @var string $licenseName */
/** @var string $licenseText */
?>
<p class="muted"><a href="<?php echo $e($basePath) ?>/admin/about"><?php echo $e($t('admin.about.license_back')) ?></a></p>
<h1><?php echo $e($t('admin.about.license')) ?></h1>
<p class="muted"><?php echo $e($copyright) ?> · <?php echo $e($licenseName) ?></p>
<div class="card">
    <pre class="license-text"><?php echo $e($licenseText) ?></pre>
</div>
