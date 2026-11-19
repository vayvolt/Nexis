<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Builder\DocumentService;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PagePath;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\Http\AdminContentLocale;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CatalogAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PageRepository $pages,
        private SitePolicy $policy,
        private DocumentService $documents,
        private LocalePathResolver $paths,
        private AdminContentLocale $contentLocale,
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
        if (!$this->policy->can($user, $site, 'catalog.manage')
            && !$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)
        ) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'catalog.manage']), 403);
        }

        $locale = $this->contentLocale->resolve($site, $request);

        return $this->responses->html($this->views->render('admin.catalog.index', [
            'user' => $user,
            'site' => $site,
            'locale' => $locale,
            'productGroups' => $this->pages->listGroupedByType($site->id, PageType::PRODUCT, $locale, $site->defaultLocale),
            'paths' => $this->paths,
            'archiveUrl' => $this->paths->url($site, $locale, CatalogPaths::archivePath(), $basePath),
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'created' => RequestInput::query($request, 'created') === '1',
        ], 'admin.layout'));
    }

    public function createForm(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, 'catalog.manage')
            && !$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)
        ) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'catalog.manage']), 403);
        }
        $locale = $this->contentLocale->resolve($site, $request);

        return $this->responses->html($this->views->render('admin.catalog.edit', [
            'user' => $user,
            'site' => $site,
            'locale' => $locale,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => '',
        ], 'admin.layout'));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, 'catalog.manage')
            && !$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)
        ) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'catalog.manage']), 403);
        }

        $locale = RequestInput::string($request, 'locale', $site->defaultLocale);
        $title = trim(RequestInput::string($request, 'title'));
        $slug = trim(RequestInput::string($request, 'slug'));
        if ($title === '') {
            return $this->responses->html($this->views->render('admin.catalog.edit', [
                'user' => $user,
                'site' => $site,
                'locale' => $locale,
                'basePath' => $basePath,
                'csrf' => (string) $request->getAttribute('csrf', ''),
                'error' => $this->ui->get($user, 'admin.error.title_required'),
            ], 'admin.layout'), 422);
        }

        $path = CatalogPaths::productPath($slug !== '' ? $slug : $title);
        $page = new Page(
            new PageId(Uuid::v7()),
            $site->id,
            Uuid::v7(),
            $locale,
            PagePath::slugFromPath($path),
            $path,
            $title,
            null,
            PageStatus::Draft,
            null,
            null,
            null,
            RequestInput::string($request, 'meta_title') ?: null,
            RequestInput::string($request, 'meta_description') ?: null,
            'index,follow',
            null,
            null,
            PageType::PRODUCT,
        );
        $this->pages->save($page);
        $this->documents->ensureDraft($page->id, $user->id);

        return $this->responses->redirect(
            $basePath . '/admin/pages/' . $page->id->value . '/builder',
        );
    }

    /**
     * @return array{0: User, 1: Site, 2: string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request): array|ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            return $this->responses->redirect((string) $request->getAttribute('base_path', '') . '/admin/login');
        }
        $site = $this->sites->installed();
        if (!$site instanceof Site) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site'), 503);
        }
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        return [$user, $site, (string) $request->getAttribute('base_path', '')];
    }
}
