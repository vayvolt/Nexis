<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\Site\Site;
use Nexis\Site\SiteId;

interface MenuRepository
{
    public function findByHandle(SiteId $siteId, string $handle, string $locale): ?Menu;

    /**
     * @return list<MenuItem>
     */
    public function itemsFor(MenuId $menuId): array;

    /**
     * @return list<NavLink>
     */
    public function resolveLinks(Site $site, string $handle, string $locale, string $basePath): array;

    public function ensure(SiteId $siteId, string $handle, string $name, string $locale): Menu;

    /**
     * @param list<array{label: string, page_id?: ?string, url?: ?string, parent?: string|int|null}> $items
     */
    public function replaceItems(MenuId $menuId, array $items): void;

    public function appendRootItem(MenuId $menuId, string $label, ?string $pageId = null, ?string $url = null): void;

    /**
     * @param callable(MenuItem): bool $predicate
     */
    public function removeRootItemsWhere(MenuId $menuId, callable $predicate): int;
}
