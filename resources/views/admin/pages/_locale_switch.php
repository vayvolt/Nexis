<?php
/**
 * Language switcher for a page translation group.
 *
 * @var \Nexis\Site\Site $site
 * @var \Nexis\Content\Page $page
 * @var array<string, \Nexis\Content\Page> $byLocale
 * @var string $basePath
 * @var string $csrf
 * @var 'edit'|'builder' $localeSurface
 * @var callable(string, array<string, scalar|null>=, ?string=): string $t
 * @var callable(mixed): string $e
 */
$byLocale = $byLocale ?? [];
$localeSurface = ($localeSurface ?? 'edit') === 'builder' ? 'builder' : 'edit';
$locales = $site->enabledLocales();
if (count($locales) < 2) {
    return;
}
?>
<div class="card locale-tabs" data-locale-tabs style="padding:.85rem 1rem;margin-bottom:1rem">
    <div class="locale-tabs__list" role="tablist" aria-label="<?php echo $e($t('admin.pages.languages')) ?>" style="margin:0;border:0;padding:0">
        <?php foreach ($locales as $loc): ?>
            <?php if (isset($byLocale[$loc->locale])): ?>
                <?php
                $alt = $byLocale[$loc->locale];
                $href = $basePath . '/admin/pages/' . $alt->id->value . ($localeSurface === 'builder' ? '/builder' : '');
                $current = $alt->id->value === $page->id->value;
                ?>
                <a
                    class="locale-tabs__tab"
                    role="tab"
                    href="<?php echo $e($href) ?>"
                    aria-selected="<?php echo $current ? 'true' : 'false' ?>"
                    <?php echo $current ? 'aria-current="page"' : '' ?>
                ><?php echo $e($t('admin.locale.' . $loc->locale, [], $loc->label)) ?></a>
            <?php else: ?>
                <form method="post" action="<?php echo $e($basePath) ?>/admin/pages/<?php echo $e($page->id->value) ?>/translations" style="margin:0;display:inline">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
                    <input type="hidden" name="locale" value="<?php echo $e($loc->locale) ?>">
                    <input type="hidden" name="copy_blocks" value="1">
                    <input type="hidden" name="return_to" value="<?php echo $e($localeSurface) ?>">
                    <button
                        type="submit"
                        class="locale-tabs__tab locale-tabs__tab--missing"
                        title="<?php echo $e($t('admin.pages.create_translation')) ?>"
                    ><?php echo $e($t('admin.locale.' . $loc->locale, [], $loc->label)) ?> +</button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php if ($localeSurface !== 'builder'): ?>
        <p class="muted" style="margin:.55rem 0 0;font-size:.85rem"><?php echo $e($t('admin.pages.language_hint')) ?></p>
    <?php endif; ?>
</div>
