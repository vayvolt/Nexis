<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;

final class PdoMenuRepository implements MenuRepository
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
        private PageRepository $pages,
        private LocalePathResolver $paths,
    ) {
    }

    public function findByHandle(SiteId $siteId, string $handle, string $locale): ?Menu
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, site_id, handle, name, locale FROM menus
             WHERE site_id = :site_id AND handle = :handle AND locale = :locale LIMIT 1',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'handle' => $handle,
            'locale' => $locale,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->mapMenu($row);
    }

    public function itemsFor(MenuId $menuId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, parent_id, label, page_id, url, sort_order FROM menu_items
             WHERE menu_id = :menu_id
             ORDER BY sort_order ASC, created_at ASC',
        );
        $stmt->execute(['menu_id' => $menuId->value]);
        /** @var array<string, MenuItem> $byId */
        $byId = [];
        /** @var list<array{id: string, parent_id: ?string}> $order */
        $order = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) $row['id'];
            $parentId = is_string($row['parent_id'] ?? null) && $row['parent_id'] !== ''
                ? (string) $row['parent_id']
                : null;
            $byId[$id] = new MenuItem(
                $id,
                (string) $row['label'],
                is_string($row['page_id'] ?? null) && $row['page_id'] !== ''
                    ? new PageId((string) $row['page_id'])
                    : null,
                is_string($row['url'] ?? null) ? (string) $row['url'] : null,
                (int) $row['sort_order'],
                $parentId,
            );
            $order[] = ['id' => $id, 'parent_id' => $parentId];
        }

        /** @var array<string, list<MenuItem>> $children */
        $children = [];
        foreach ($order as $meta) {
            $parent = $meta['parent_id'];
            if ($parent === null || !isset($byId[$parent])) {
                continue;
            }
            $children[$parent][] = $byId[$meta['id']];
        }

        $roots = [];
        foreach ($order as $meta) {
            $parent = $meta['parent_id'];
            if ($parent !== null && isset($byId[$parent])) {
                continue;
            }
            $item = $byId[$meta['id']];
            $roots[] = new MenuItem(
                $item->id,
                $item->label,
                $item->pageId,
                $item->url,
                $item->sortOrder,
                null,
                $children[$item->id] ?? [],
            );
        }

        return $roots;
    }

    public function resolveLinks(Site $site, string $handle, string $locale, string $basePath): array
    {
        $menu = $this->findByHandle($site->id, $handle, $locale);
        if ($menu === null) {
            return [];
        }

        return $this->mapItemsToLinks($site, $this->itemsFor($menu->id), $basePath);
    }

    public function ensure(SiteId $siteId, string $handle, string $name, string $locale): Menu
    {
        $existing = $this->findByHandle($siteId, $handle, $locale);
        if ($existing !== null) {
            return $existing;
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $id = Uuid::v7();
        $stmt = $this->pdo->prepare(
            'INSERT INTO menus (id, site_id, handle, name, locale, created_at, updated_at)
             VALUES (:id, :site_id, :handle, :name, :locale, :created_at, :updated_at)',
        );
        $stmt->execute([
            'id' => $id,
            'site_id' => $siteId->value,
            'handle' => $handle,
            'name' => $name,
            'locale' => $locale,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new Menu(new MenuId($id), $siteId, $handle, $name, $locale);
    }

    public function replaceItems(MenuId $menuId, array $items): void
    {
        $this->pdo->beginTransaction();
        try {
            $del = $this->pdo->prepare('DELETE FROM menu_items WHERE menu_id = :menu_id');
            $del->execute(['menu_id' => $menuId->value]);
            $ins = $this->pdo->prepare(
                'INSERT INTO menu_items
                    (id, menu_id, parent_id, page_id, label, url, sort_order, created_at, updated_at)
                 VALUES
                    (:id, :menu_id, :parent_id, :page_id, :label, :url, :sort_order, :created_at, :updated_at)',
            );
            $now = $this->clock->now()->format('Y-m-d H:i:s.v');
            /** @var array<int, string> $rowIds */
            $rowIds = [];
            $order = 0;
            // Roots first
            foreach ($items as $i => $item) {
                $parentRef = $item['parent'] ?? null;
                if ($parentRef !== null && $parentRef !== '') {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $id = Uuid::v7();
                $rowIds[(int) $i] = $id;
                $pageId = isset($item['page_id']) && is_string($item['page_id']) && $item['page_id'] !== ''
                    ? $item['page_id']
                    : null;
                $url = isset($item['url']) && is_string($item['url']) && trim($item['url']) !== ''
                    ? trim($item['url'])
                    : null;
                $ins->execute([
                    'id' => $id,
                    'menu_id' => $menuId->value,
                    'parent_id' => null,
                    'page_id' => $pageId,
                    'label' => $label,
                    'url' => $pageId !== null ? null : $url,
                    'sort_order' => $order++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            // Children (one level)
            foreach ($items as $i => $item) {
                $parentRef = $item['parent'] ?? null;
                if ($parentRef === null || $parentRef === '') {
                    continue;
                }
                $parentIndex = (int) $parentRef;
                if (!isset($rowIds[$parentIndex])) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $pageId = isset($item['page_id']) && is_string($item['page_id']) && $item['page_id'] !== ''
                    ? $item['page_id']
                    : null;
                $url = isset($item['url']) && is_string($item['url']) && trim($item['url']) !== ''
                    ? trim($item['url'])
                    : null;
                $ins->execute([
                    'id' => Uuid::v7(),
                    'menu_id' => $menuId->value,
                    'parent_id' => $rowIds[$parentIndex],
                    'page_id' => $pageId,
                    'label' => $label,
                    'url' => $pageId !== null ? null : $url,
                    'sort_order' => $order++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function appendRootItem(MenuId $menuId, string $label, ?string $pageId = null, ?string $url = null): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) FROM menu_items WHERE menu_id = :menu_id AND parent_id IS NULL',
        );
        $stmt->execute(['menu_id' => $menuId->value]);
        $sort = ((int) $stmt->fetchColumn()) + 1;
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $ins = $this->pdo->prepare(
            'INSERT INTO menu_items
                (id, menu_id, parent_id, page_id, label, url, sort_order, created_at, updated_at)
             VALUES
                (:id, :menu_id, NULL, :page_id, :label, :url, :sort_order, :created_at, :updated_at)',
        );
        $ins->execute([
            'id' => Uuid::v7(),
            'menu_id' => $menuId->value,
            'page_id' => $pageId !== null && $pageId !== '' ? $pageId : null,
            'label' => $label,
            'url' => $pageId !== null && $pageId !== '' ? null : $url,
            'sort_order' => $sort,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function removeRootItemsWhere(MenuId $menuId, callable $predicate): int
    {
        $removed = 0;
        foreach ($this->itemsFor($menuId) as $item) {
            if ($item->parentId !== null) {
                continue;
            }
            if (!$predicate($item)) {
                continue;
            }
            $stmt = $this->pdo->prepare('DELETE FROM menu_items WHERE id = :id AND menu_id = :menu_id');
            $stmt->execute([
                'id' => $item->id,
                'menu_id' => $menuId->value,
            ]);
            $removed += $stmt->rowCount();
        }

        return $removed;
    }

    /**
     * @param list<MenuItem> $items
     * @return list<NavLink>
     */
    private function mapItemsToLinks(Site $site, array $items, string $basePath): array
    {
        $links = [];
        foreach ($items as $item) {
            $url = $this->resolveItemUrl($site, $item, $basePath);
            $childLinks = $this->mapItemsToLinks($site, $item->children, $basePath);
            if (($url === null || $url === '') && $childLinks === []) {
                continue;
            }
            $links[] = new NavLink(
                $item->label,
                $url ?? '#',
                $childLinks,
            );
        }

        return $links;
    }

    private function resolveItemUrl(Site $site, MenuItem $item, string $basePath): ?string
    {
        if ($item->pageId !== null) {
            $page = $this->pages->findById($item->pageId);
            if ($page === null || !$page->siteId->equals($site->id)) {
                return null;
            }
            if (!$page->isPublished) {
                return null;
            }

            return $this->paths->url($site, $page->locale, $page->path, $basePath);
        }

        $url = trim((string) ($item->url ?? ''));
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '#')) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return rtrim($basePath, '/') . $url;
        }

        return rtrim($basePath, '/') . '/' . ltrim($url, '/');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapMenu(array $row): Menu
    {
        return new Menu(
            new MenuId((string) $row['id']),
            new SiteId((string) $row['site_id']),
            (string) $row['handle'],
            (string) $row['name'],
            (string) $row['locale'],
        );
    }
}
