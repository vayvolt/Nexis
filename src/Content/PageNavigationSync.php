<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\I18n\PublicUi;
use Nexis\Site\Site;

/**
 * Adds a page to the primary navigation for its locale (idempotent).
 */
final class PageNavigationSync
{
    public function __construct(
        private MenuRepository $menus,
        private PublicUi $ui,
    ) {
    }

    public function addToPrimary(Site $site, Page $page): void
    {
        if (!$site->id->equals($page->siteId)) {
            return;
        }
        if ($site->locale($page->locale) === null) {
            return;
        }
        // Blog-/Katalog-Einträge gehören nicht in die Seiten-Navigation.
        if ($page->type !== PageType::PAGE) {
            return;
        }

        $label = $this->ui->get($page->locale, 'admin.menus.primary');
        $menu = $this->menus->ensure($site->id, 'primary', $label, $page->locale);
        if ($this->hasPageLink($menu->id, $page->id)) {
            return;
        }

        $this->menus->appendRootItem($menu->id, $page->title, $page->id->value, null);
    }

    private function hasPageLink(MenuId $menuId, PageId $pageId): bool
    {
        foreach ($this->menus->itemsFor($menuId) as $item) {
            if ($this->itemLinksToPage($item, $pageId)) {
                return true;
            }
        }

        return false;
    }

    private function itemLinksToPage(MenuItem $item, PageId $pageId): bool
    {
        if ($item->pageId !== null && $item->pageId->equals($pageId)) {
            return true;
        }
        foreach ($item->children as $child) {
            if ($this->itemLinksToPage($child, $pageId)) {
                return true;
            }
        }

        return false;
    }
}
