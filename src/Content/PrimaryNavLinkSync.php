<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;

/**
 * Ensures or removes a custom URL root item in the primary nav for all locales.
 */
final class PrimaryNavLinkSync
{
    public function __construct(
        private SiteRepository $sites,
        private MenuRepository $menus,
        private LocalePathResolver $paths,
    ) {
    }

    /**
     * @param callable(string $locale): string $labelForLocale
     */
    public function ensure(SiteId $siteId, string $pagePath, callable $labelForLocale): void
    {
        $site = $this->sites->installed();
        if ($site === null || !$site->id->equals($siteId)) {
            return;
        }
        $pagePath = $this->normalizePath($pagePath);

        foreach ($site->enabledLocales() as $locale) {
            $menu = $this->menus->findByHandle($site->id, 'primary', $locale->locale);
            if ($menu === null) {
                continue;
            }
            $archiveUrl = $this->archiveUrl($site, $locale->locale, $pagePath);
            if ($this->hasLink($menu->id, $archiveUrl, $pagePath)) {
                continue;
            }
            $label = trim($labelForLocale($locale->locale));
            if ($label === '') {
                $label = ltrim($pagePath, '/');
            }
            $this->menus->appendRootItem($menu->id, $label, null, $archiveUrl);
        }
    }

    public function remove(SiteId $siteId, string $pagePath): void
    {
        $site = $this->sites->installed();
        if ($site === null || !$site->id->equals($siteId)) {
            return;
        }
        $pagePath = $this->normalizePath($pagePath);

        foreach ($site->enabledLocales() as $locale) {
            $menu = $this->menus->findByHandle($site->id, 'primary', $locale->locale);
            if ($menu === null) {
                continue;
            }
            $archiveUrl = $this->archiveUrl($site, $locale->locale, $pagePath);
            $this->menus->removeRootItemsWhere(
                $menu->id,
                fn (MenuItem $item): bool => $this->isLinkItem($item, $archiveUrl, $pagePath),
            );
        }
    }

    public function archiveUrl(Site $site, string $locale, string $pagePath): string
    {
        return $this->paths->url($site, $locale, $this->normalizePath($pagePath), '');
    }

    public static function looksLikePathUrl(string $url, string $pagePath): bool
    {
        $slug = trim($pagePath, '/');
        if ($slug === '') {
            return false;
        }
        $normalized = strtolower(rtrim(trim($url), '/'));
        if ($normalized === '') {
            return false;
        }
        $quoted = preg_quote(strtolower($slug), '#');

        return (bool) preg_match('#(?:^|/)(?:[a-z]{2}(?:-[a-z]{2})?/)?' . $quoted . '$#', $normalized);
    }

    private function normalizePath(string $pagePath): string
    {
        return '/' . ltrim(trim($pagePath), '/');
    }

    private function hasLink(MenuId $menuId, string $archiveUrl, string $pagePath): bool
    {
        foreach ($this->menus->itemsFor($menuId) as $item) {
            if ($this->isLinkItem($item, $archiveUrl, $pagePath)) {
                return true;
            }
        }

        return false;
    }

    private function isLinkItem(MenuItem $item, string $archiveUrl, string $pagePath): bool
    {
        if ($item->pageId !== null) {
            return false;
        }
        $url = trim((string) ($item->url ?? ''));
        if ($url === '') {
            return false;
        }
        if (rtrim($url, '/') === rtrim($archiveUrl, '/')) {
            return true;
        }

        return self::looksLikePathUrl($url, $pagePath);
    }
}
