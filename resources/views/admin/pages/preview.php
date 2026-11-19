<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
?>
<h1><?php echo $e($t('admin.pages.preview_title', ['title' => $page->title])) ?></h1>
<p class="muted"><a href="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/builder"><?php echo $e($t('admin.pages.back_builder')) ?></a></p>
<article class="card">
    <?php echo $contentHtml ?>
</article>
