<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($t('admin.blog.create_title')) ?></h1>
<?php if (($error ?? '') !== ''): ?><p class="error"><?php echo $e($error) ?></p><?php endif; ?>

<form method="post" action="<?php echo $e($basePath) ?>/admin/blog" class="card">
    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
    <input type="hidden" name="locale" value="<?php echo $e($locale) ?>">

    <label for="title"><?php echo $e($t('admin.common.title')) ?></label>
    <input id="title" name="title" type="text" required maxlength="190" autofocus>

    <label for="slug"><?php echo $e($t('admin.blog.slug_optional')) ?></label>
    <input id="slug" name="slug" type="text" maxlength="190" placeholder="mein-beitrag">
    <p class="muted"><?php echo $e($t('admin.blog.slug_hint')) ?></p>
    <?php if (count($site->enabledLocales()) > 1): ?>
        <p class="muted"><?php echo $e($t('admin.blog.create_all_locales')) ?></p>
    <?php endif; ?>

    <label for="meta_title"><?php echo $e($t('admin.blog.seo_title')) ?></label>
    <input id="meta_title" name="meta_title" type="text" maxlength="190">

    <label for="meta_description"><?php echo $e($t('admin.blog.seo_desc')) ?></label>
    <textarea id="meta_description" name="meta_description" rows="3" maxlength="500"></textarea>

    <p style="margin-top:1rem">
        <button type="submit" class="btn"><?php echo $e($t('admin.blog.create_open')) ?></button>
        <a class="btn btn-small" href="<?php echo $e($basePath) ?>/admin/blog?locale=<?php echo $e($locale) ?>"><?php echo $e($t('admin.common.cancel')) ?></a>
    </p>
</form>
