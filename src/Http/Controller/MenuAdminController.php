<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Cache\PageCache;
use Nexis\Content\MenuRepository;
use Nexis\Content\PageRepository;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MenuAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PageRepository $pages,
        private MenuRepository $menus,
        private SitePolicy $policy,
        private PageCache $cache,
        private AdminUi $ui,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $handle = RequestInput::query($request, 'handle', 'primary');
        $handles = $this->handles($user);
        if (!isset($handles[$handle])) {
            $handle = 'primary';
        }

        $locales = $site->enabledLocales();
        $minRows = $handle === 'footer' ? 6 : 8;
        $rowCount = $minRows;
        /** @var array<string, array{items: list<array{item: mixed, parent: string}>, pages: list<\Nexis\Content\Page>}> $byLocale */
        $byLocale = [];
        foreach ($locales as $loc) {
            $menu = $this->menus->ensure($site->id, $handle, $handles[$handle], $loc->locale);
            $items = $this->flattenItems($this->menus->itemsFor($menu->id));
            $byLocale[$loc->locale] = [
                'items' => $items,
                'pages' => $this->pages->listBySite($site->id, $loc->locale),
            ];
            $rowCount = max($rowCount, count($items));
        }

        return $this->responses->html($this->views->render('admin.menus.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'handle' => $handle,
            'handles' => $handles,
            'byLocale' => $byLocale,
            'rowCount' => $rowCount,
            'error' => '',
            'saved' => RequestInput::query($request, 'saved') === '1',
        ], 'admin.layout'));
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $handle = RequestInput::string($request, 'handle', 'primary');
        $handles = $this->handles($user);
        if (!isset($handles[$handle])) {
            $handle = 'primary';
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $rawLabels = is_array($body['label'] ?? null) ? $body['label'] : [];
        $rawPages = is_array($body['page_id'] ?? null) ? $body['page_id'] : [];
        $rawUrls = is_array($body['url'] ?? null) ? $body['url'] : [];
        $rawParents = is_array($body['parent'] ?? null) ? $body['parent'] : [];

        foreach ($site->enabledLocales() as $loc) {
            $code = $loc->locale;
            $labels = is_array($rawLabels[$code] ?? null) ? $rawLabels[$code] : [];
            $pageIds = is_array($rawPages[$code] ?? null) ? $rawPages[$code] : [];
            $urls = is_array($rawUrls[$code] ?? null) ? $rawUrls[$code] : [];
            $menu = $this->menus->ensure($site->id, $handle, $handles[$handle], $code);
            $items = [];
            foreach (array_keys($labels) as $idx) {
                $label = trim((string) ($labels[$idx] ?? ''));
                if ($label === '') {
                    continue;
                }
                $pageId = trim((string) ($pageIds[$idx] ?? ''));
                $url = trim((string) ($urls[$idx] ?? ''));
                $parent = trim((string) ($rawParents[$idx] ?? ''));
                $items[(int) $idx] = [
                    'label' => $label,
                    'page_id' => $pageId !== '' ? $pageId : null,
                    'url' => $pageId === '' ? ($url !== '' ? $url : null) : null,
                    'parent' => $parent !== '' ? $parent : null,
                ];
            }
            $this->menus->replaceItems($menu->id, array_values($items));
        }

        $this->cache->invalidateSite($site->id);

        return $this->responses->redirect(
            $basePath . '/admin/menus?handle=' . rawurlencode($handle) . '&saved=1',
        );
    }

    /**
     * @param list<\Nexis\Content\MenuItem> $tree
     * @return list<array{item: \Nexis\Content\MenuItem, parent: string}>
     */
    private function flattenItems(array $tree): array
    {
        $rows = [];
        foreach ($tree as $root) {
            $parentIndex = (string) count($rows);
            $rows[] = ['item' => $root, 'parent' => ''];
            foreach ($root->children as $child) {
                $rows[] = ['item' => $child, 'parent' => $parentIndex];
            }
        }

        return $rows;
    }

    /**
     * @return array{User, \Nexis\Site\Site, string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request): array|ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }

        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site'), 503);
        }
        if (!$this->policy->can($user, $site, Permission::CONTENT_MENU_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.menu.manage']), 403);
        }

        return [$user, $site, $basePath];
    }

    /**
     * @return array<string, string>
     */
    private function handles(User $user): array
    {
        return [
            'primary' => $this->ui->get($user, 'admin.menus.primary'),
            'footer' => $this->ui->get($user, 'admin.menus.footer'),
        ];
    }
}
