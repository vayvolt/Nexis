<?php
/** @var callable(string, array<string, scalar|null>=, ?string=): string $t */
$t = $t ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
$logoUrl = (string) ($overrides['brand.logoUrl'] ?? $tokens['brand.logoUrl'] ?? '');
$logoMedia = $logoMedia ?? [];
$themeTabs = [
    'theme' => $t('admin.theme.active'),
    'layout' => $t('admin.theme.layout'),
    'colors' => $t('admin.theme.colors'),
];
?>
<h1><?php echo $e($t('admin.theme.title')) ?></h1>
<?php if ($notice !== ''): ?><p class="flash flash--success" role="status"><?php echo $e($notice) ?></p><?php endif; ?>

<div class="card locale-tabs" data-locale-tabs data-tabs-hash>
    <div class="locale-tabs__list" role="tablist" aria-label="<?php echo $e($t('admin.theme.title')) ?>">
        <?php $tabIndex = 0; foreach ($themeTabs as $tabId => $tabLabel): ?>
            <button
                type="button"
                class="locale-tabs__tab"
                role="tab"
                id="theme-tab-<?php echo $e($tabId) ?>"
                data-locale-tab="<?php echo $e($tabId) ?>"
                aria-controls="theme-panel-<?php echo $e($tabId) ?>"
                aria-selected="<?php echo $tabIndex === 0 ? 'true' : 'false' ?>"
                tabindex="<?php echo $tabIndex === 0 ? '0' : '-1' ?>"
            ><?php echo $e($tabLabel) ?></button>
        <?php $tabIndex++; endforeach; ?>
    </div>

    <div
        class="locale-tabs__panel"
        role="tabpanel"
        id="theme-panel-theme"
        data-locale-panel="theme"
        aria-labelledby="theme-tab-theme"
    >
        <p class="muted"><?php echo $e($t('admin.theme.active_hint')) ?></p>
        <form method="post" action="<?php echo $e($basePath) ?>/admin/theme/activate">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
            <label for="theme">Theme</label>
            <select id="theme" name="theme">
                <?php foreach ($themes as $theme): ?>
                    <option value="<?php echo $e($theme->id) ?>" <?php echo $theme->id === $activeTheme ? 'selected' : '' ?>>
                        <?php echo $e($theme->name) ?> (<?php echo $e($theme->id) ?><?php echo $theme->extends ? ' · extends ' . $e($theme->extends) : '' ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p><button type="submit"><?php echo $e($t('admin.theme.activate')) ?></button></p>
        </form>
    </div>

    <form method="post" action="<?php echo $e($basePath) ?>/admin/theme/branding">
        <input type="hidden" name="_csrf" value="<?php echo $e($csrf) ?>">
        <input type="hidden" name="return_tab" value="layout" data-tabs-return="layout,colors">

        <div
            class="locale-tabs__panel"
            role="tabpanel"
            id="theme-panel-layout"
            data-locale-panel="layout"
            aria-labelledby="theme-tab-layout"
            hidden
        >
            <p class="muted"><?php echo $e($t('admin.theme.layout_hint')) ?></p>
            <div class="row">
                <div>
                    <label for="token_layout_nav_position"><?php echo $e($t('admin.theme.nav_position')) ?></label>
                    <select id="token_layout_nav_position" name="token_layout_nav_position">
                        <?php
                        $navPos = $overrides['layout.nav.position'] ?? $tokens['layout.nav.position'] ?? 'right';
                        foreach ([
                            'right' => $t('admin.theme.nav.right'),
                            'left' => $t('admin.theme.nav.left'),
                            'below' => $t('admin.theme.nav.below'),
                        ] as $value => $label):
                            ?>
                            <option value="<?php echo $e($value) ?>" <?php echo $navPos === $value ? 'selected' : '' ?>><?php echo $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="token_layout_footer_position"><?php echo $e($t('admin.theme.footer_position')) ?></label>
                    <select id="token_layout_footer_position" name="token_layout_footer_position">
                        <?php
                        $footerPos = $overrides['layout.footer.position'] ?? $tokens['layout.footer.position'] ?? 'split';
                        foreach ([
                            'split' => $t('admin.theme.footer.split'),
                            'center' => $t('admin.theme.footer.center'),
                            'stack' => $t('admin.theme.footer.stack'),
                            'reverse' => $t('admin.theme.footer.reverse'),
                        ] as $value => $label):
                            ?>
                            <option value="<?php echo $e($value) ?>" <?php echo $footerPos === $value ? 'selected' : '' ?>><?php echo $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="token_layout_pageTitle"><?php echo $e($t('admin.theme.page_title')) ?></label>
                    <select id="token_layout_pageTitle" name="token_layout_pageTitle">
                        <?php $pageTitle = $overrides['layout.pageTitle'] ?? $tokens['layout.pageTitle'] ?? 'hide'; ?>
                        <option value="hide" <?php echo $pageTitle === 'hide' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.page_title.hide')) ?></option>
                        <option value="show" <?php echo $pageTitle === 'show' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.page_title.show')) ?></option>
                    </select>
                </div>
                <div></div>
            </div>
            <div class="row">
                <div>
                    <label for="token_layout_footer_showSiteName"><?php echo $e($t('admin.theme.footer_show_site_name')) ?></label>
                    <select id="token_layout_footer_showSiteName" name="token_layout_footer_showSiteName">
                        <?php $footerSite = $overrides['layout.footer.showSiteName'] ?? $tokens['layout.footer.showSiteName'] ?? 'true'; ?>
                        <option value="true" <?php echo $footerSite === 'true' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.show')) ?></option>
                        <option value="false" <?php echo $footerSite === 'false' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.hide')) ?></option>
                    </select>
                </div>
                <div>
                    <label for="token_layout_footer_showThemeName"><?php echo $e($t('admin.theme.footer_show_theme_name')) ?></label>
                    <select id="token_layout_footer_showThemeName" name="token_layout_footer_showThemeName">
                        <?php $footerTheme = $overrides['layout.footer.showThemeName'] ?? $tokens['layout.footer.showThemeName'] ?? 'true'; ?>
                        <option value="true" <?php echo $footerTheme === 'true' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.show')) ?></option>
                        <option value="false" <?php echo $footerTheme === 'false' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.hide')) ?></option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="token_brand_showSiteName"><?php echo $e($t('admin.theme.show_site_name')) ?></label>
                    <select id="token_brand_showSiteName" name="token_brand_showSiteName">
                        <?php $showName = $overrides['brand.showSiteName'] ?? $tokens['brand.showSiteName'] ?? 'true'; ?>
                        <option value="true" <?php echo $showName === 'true' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.show')) ?></option>
                        <option value="false" <?php echo $showName === 'false' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.hide_logo_only')) ?></option>
                    </select>
                </div>
                <div>
                    <label for="token_brand_logoInNav"><?php echo $e($t('admin.theme.logo_in_nav')) ?></label>
                    <select id="token_brand_logoInNav" name="token_brand_logoInNav">
                        <?php $logoInNav = $overrides['brand.logoInNav'] ?? $tokens['brand.logoInNav'] ?? 'false'; ?>
                        <option value="false" <?php echo $logoInNav === 'false' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.logo_in_nav.no')) ?></option>
                        <option value="true" <?php echo $logoInNav === 'true' ? 'selected' : '' ?>><?php echo $e($t('admin.theme.logo_in_nav.yes')) ?></option>
                    </select>
                </div>
            </div>
            <label for="token_layout_max"><?php echo $e($t('admin.theme.content_width')) ?></label>
            <input id="token_layout_max" name="token_layout_max" value="<?php echo $e($overrides['layout.max'] ?? $tokens['layout.max'] ?? '') ?>" placeholder="48rem">
        </div>

        <div
            class="locale-tabs__panel"
            role="tabpanel"
            id="theme-panel-colors"
            data-locale-panel="colors"
            aria-labelledby="theme-tab-colors"
            hidden
        >
            <p class="muted"><?php echo $e($t('admin.theme.colors_hint')) ?></p>
            <div class="row">
                <div>
                    <label for="token_color_brand_primary"><?php echo $e($t('admin.theme.primary')) ?></label>
                    <input id="token_color_brand_primary" name="token_color_brand_primary" value="<?php echo $e($overrides['color.brand.primary'] ?? $tokens['color.brand.primary'] ?? '') ?>">
                </div>
                <div>
                    <label for="token_color_brand_accent"><?php echo $e($t('admin.theme.accent')) ?></label>
                    <input id="token_color_brand_accent" name="token_color_brand_accent" value="<?php echo $e($overrides['color.brand.accent'] ?? $tokens['color.brand.accent'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="token_color_surface"><?php echo $e($t('admin.theme.surface')) ?></label>
                    <input id="token_color_surface" name="token_color_surface" value="<?php echo $e($overrides['color.surface'] ?? $tokens['color.surface'] ?? '') ?>">
                </div>
                <div>
                    <label for="token_color_text"><?php echo $e($t('admin.theme.text')) ?></label>
                    <input id="token_color_text" name="token_color_text" value="<?php echo $e($overrides['color.text'] ?? $tokens['color.text'] ?? '') ?>">
                </div>
            </div>
            <label for="token_brand_logoUrl"><?php echo $e($t('admin.theme.logo_url')) ?></label>
            <div class="theme-logo-field" data-logo-picker>
                <div class="theme-logo-url-row">
                    <input id="token_brand_logoUrl" name="token_brand_logoUrl" value="<?php echo $e($logoUrl) ?>" placeholder="/media/…">
                    <a class="btn btn-small btn-muted" href="<?php echo $e($basePath) ?>/admin/media"><?php echo $e($t('admin.nav.media')) ?></a>
                </div>
                <p class="muted" style="margin:.35rem 0 0"><?php echo $e($t('admin.theme.logo_hint')) ?></p>
                <div class="theme-logo-picker" role="group" aria-label="<?php echo $e($t('admin.theme.logo_pick')) ?>">
                    <button type="button" class="theme-logo-pick<?php echo $logoUrl === '' ? ' is-active' : '' ?>" data-logo-clear>
                        <span class="theme-logo-pick__empty"><?php echo $e($t('admin.theme.logo_none')) ?></span>
                    </button>
                    <?php foreach ($logoMedia as $asset): ?>
                        <?php $isActive = $logoUrl !== '' && $logoUrl === ($asset['url'] ?? ''); ?>
                        <button
                            type="button"
                            class="theme-logo-pick<?php echo $isActive ? ' is-active' : '' ?>"
                            data-logo-url="<?php echo $e($asset['url'] ?? '') ?>"
                            title="<?php echo $e($asset['name'] ?? '') ?>"
                        >
                            <img src="<?php echo $e($asset['thumbUrl'] ?? $asset['url'] ?? '') ?>" alt="<?php echo $e($asset['alt'] ?? $asset['name'] ?? '') ?>">
                            <span><?php echo $e($asset['name'] ?? '') ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <?php if ($logoMedia === []): ?>
                    <p class="muted" style="margin:.5rem 0 0"><?php echo $e($t('admin.theme.logo_empty')) ?></p>
                <?php endif; ?>
            </div>
            <label for="token_font_sans"><?php echo $e($t('admin.theme.font')) ?></label>
            <input id="token_font_sans" name="token_font_sans" value="<?php echo $e($overrides['font.sans'] ?? $tokens['font.sans'] ?? '') ?>">
            <label for="custom_css"><?php echo $e($t('admin.theme.custom_css')) ?></label>
            <textarea id="custom_css" name="custom_css" rows="6"><?php echo $e($customCss) ?></textarea>
        </div>

        <p class="theme-design-actions" style="margin:1rem 0 0"><button type="submit"><?php echo $e($t('admin.theme.save_design')) ?></button></p>
    </form>
</div>

<p class="muted"><?php echo $e($t('admin.theme.footer_hint')) ?> <a href="<?php echo $e($basePath) ?>/admin/menus?handle=footer"><?php echo $e($t('admin.nav.menus')) ?></a></p>

<style>
.theme-logo-url-row { display: flex; gap: .5rem; align-items: center; }
.theme-logo-url-row input { margin: 0; flex: 1; }
.theme-logo-picker {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(5.5rem, 1fr));
  gap: .45rem;
  margin-top: .65rem;
}
.theme-logo-pick {
  display: flex;
  flex-direction: column;
  gap: .25rem;
  align-items: stretch;
  padding: .35rem;
  border: 1px solid var(--line, #d6d3d1);
  border-radius: 6px;
  background: var(--card, #fff);
  color: inherit;
  cursor: pointer;
  font: inherit;
  font-size: .7rem;
  text-align: center;
}
.theme-logo-pick img {
  width: 100%;
  aspect-ratio: 1;
  object-fit: contain;
  border-radius: 4px;
  background: #f5f5f4;
}
.theme-logo-pick span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.theme-logo-pick__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  border-radius: 4px;
  background: #f5f5f4;
  color: var(--muted, #78716c);
  font-size: .75rem;
  white-space: normal;
  padding: .35rem;
}
.theme-logo-pick.is-active {
  border-color: var(--accent, #1b4d3e);
  box-shadow: 0 0 0 2px rgba(27, 77, 62, 0.18);
}
</style>
<script src="<?php echo $e($basePath) ?>/assets/admin/theme-logo.js" defer></script>
