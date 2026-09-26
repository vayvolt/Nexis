<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Builder\DocumentService;
use Nexis\Builder\PublishService;
use Nexis\Cache\PageCache;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageNavigationSync;
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
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PageAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PageRepository $pages,
        private SitePolicy $policy,
        private DocumentService $documents,
        private PublishService $publish,
        private LocalePathResolver $paths,
        private PageCache $cache,
        private PageNavigationSync $navigation,
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
        $locale = $this->contentLocale->resolve($site, $request);
        $query = $request->getQueryParams();
        $pageGroups = $this->pages->listGroupedByType($site->id, PageType::PAGE, $locale, $site->defaultLocale);

        return $this->responses->html($this->views->render('admin.pages.index', [
            'user' => $user,
            'site' => $site,
            'locale' => $locale,
            'pageGroups' => $pageGroups,
            'paths' => $this->paths,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'deleted' => ($query['deleted'] ?? '') === '1',
            'error' => is_string($query['error'] ?? null) ? (string) $query['error'] : '',
            'canEditContent' => $this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT),
        ], 'admin.layout'));
    }

    public function createForm(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }
        $locale = $this->contentLocale->resolve($site, $request);
        $title = $this->ui->get($user, 'admin.pages.untitled');
        $path = $this->uniquePath($site->id, $locale, PagePath::fromSlug($title));
        $translationGroupId = Uuid::v7();
        $slugValue = PagePath::slugFromPath($path);

        $locales = [$locale];
        foreach ($site->enabledLocales() as $loc) {
            if ($loc->locale !== $locale) {
                $locales[] = $loc->locale;
            }
        }

        $primaryId = null;
        foreach ($locales as $targetLocale) {
            if ($this->pages->findByPath($site->id, $targetLocale, $path) !== null) {
                continue;
            }
            $page = new Page(
                new PageId(Uuid::v7()),
                $site->id,
                $translationGroupId,
                $targetLocale,
                $slugValue,
                $path,
                $title,
                null,
                PageStatus::Draft,
            );
            $this->pages->save($page);
            $this->documents->ensureDraft($page->id, $user->id);
            $this->navigation->addToPrimary($site, $page);
            if ($targetLocale === $locale) {
                $primaryId = $page->id->value;
            }
        }

        $this->cache->invalidateSite($site->id);
        $this->contentLocale->remember($locale);

        if ($primaryId === null) {
            return $this->responses->redirect($basePath . '/admin/pages?locale=' . rawurlencode($locale)
                . '&error=' . rawurlencode($this->ui->get($user, 'admin.error.path_taken')));
        }

        return $this->responses->redirect($basePath . '/admin/pages/' . $primaryId . '/builder');
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        // Legacy POST create: same instant draft → builder flow.
        return $this->createForm($request);
    }

    public function editForm(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        $this->contentLocale->remember($page->locale);

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder');
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }
        $title = RequestInput::string($request, 'title');
        if ($title === '') {
            return $this->responses->html($this->views->render('admin.pages.edit', [
                'user' => $user,
                'site' => $site,
                'page' => $page,
                'locale' => $page->locale,
                'byLocale' => $this->alternatesByLocale($page),
                'basePath' => $basePath,
                'csrf' => (string) $request->getAttribute('csrf', ''),
                'error' => $this->ui->get($user, 'admin.error.title_required'),
            ], 'admin.layout'), 422);
        }

        $path = PagePath::replaceLeaf(
            $page->path,
            RequestInput::string($request, 'slug') !== '' ? RequestInput::string($request, 'slug') : $title,
        );
        $statusRaw = RequestInput::string($request, 'status', 'draft');
        $publishedRevisionId = $page->publishedRevisionId;
        $publishedSnapshotId = $page->publishedSnapshotId;
        $scheduledAt = null;
        $scheduledBy = null;
        $unpublishAt = $page->unpublishAt;
        $unpublishBy = $page->unpublishBy;
        $wantsPublished = false;

        if ($statusRaw === 'scheduled') {
            if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH)) {
                return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.publish']), 403);
            }
            $scheduledAt = $this->parseScheduledAt(RequestInput::string($request, 'scheduled_at'));
            if ($scheduledAt === null) {
                return $this->responses->html($this->views->render('admin.pages.edit', [
                    'user' => $user,
                    'site' => $site,
                    'page' => $page,
                    'locale' => $page->locale,
                    'byLocale' => $this->alternatesByLocale($page),
                    'basePath' => $basePath,
                    'csrf' => (string) $request->getAttribute('csrf', ''),
                    'error' => $this->ui->get($user, 'admin.error.schedule_invalid'),
                ], 'admin.layout'), 422);
            }
            // Bereits live: Status bleibt published, nur Update planen. Sonst: scheduled (noch nicht öffentlich).
            $status = $publishedSnapshotId !== null ? PageStatus::Published : PageStatus::Scheduled;
            $scheduledBy = $user->id;
            $this->documents->ensureDraft($page->id, $user->id);
        } elseif ($statusRaw === 'published') {
            $wantsPublished = true;
            $status = PageStatus::Published;
            if ($publishedSnapshotId === null) {
                $status = PageStatus::Draft;
            }
        } elseif ($statusRaw === 'in_review' && $page->status === PageStatus::InReview) {
            $status = PageStatus::InReview;
        } else {
            $status = PageStatus::Draft;
            $publishedRevisionId = null;
            $publishedSnapshotId = null;
            $unpublishAt = null;
            $unpublishBy = null;
        }

        $updated = new Page(
            $page->id,
            $page->siteId,
            $page->translationGroupId,
            $page->locale,
            PagePath::slugFromPath($path),
            $path,
            $title,
            RequestInput::string($request, 'body_text') ?: null,
            $status,
            $publishedRevisionId,
            $publishedSnapshotId,
            $page->searchText,
            RequestInput::string($request, 'meta_title') ?: null,
            RequestInput::string($request, 'meta_description') ?: null,
            $this->normalizeRobots(RequestInput::string($request, 'robots', $page->robots)),
            $scheduledAt,
            $scheduledBy,
            $page->type,
            null,
            null,
            null,
            $unpublishAt,
            $unpublishBy,
            $page->focusKeyword,
        );
        $this->pages->save($updated);
        $bodyText = RequestInput::string($request, 'body_text');
        $this->documents->applyLegacyBodyText($updated->id, $bodyText, $user->id);
        if ($wantsPublished) {
            $fresh = $this->pages->findById($updated->id) ?? $updated;
            $this->publish->publish($fresh, null, $user->id, $basePath);
        }

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?saved=1');
    }

    public function translate(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }
        $target = RequestInput::string($request, 'locale');
        if ($site->locale($target) === null) {
            return $this->responses->html('Unbekannte Locale.', 422);
        }

        $existing = array_find(
            $this->pages->alternates($page),
            static fn (Page $alt): bool => $alt->locale === $target,
        );
        $returnTo = RequestInput::string($request, 'return_to');
        $suffix = $returnTo === 'edit' ? '' : '/builder';
        if ($existing instanceof Page) {
            return $this->responses->redirect($basePath . '/admin/pages/' . $existing->id->value . $suffix);
        }

        $copyBlocks = RequestInput::string($request, 'copy_blocks') === '1';
        $translation = new Page(
            new PageId(Uuid::v7()),
            $page->siteId,
            $page->translationGroupId,
            $target,
            $page->slug,
            $page->path,
            $page->title,
            $page->bodyText,
            PageStatus::Draft,
            null,
            null,
            null,
            $page->metaTitle,
            $page->metaDescription,
            $page->robots,
            null,
            null,
            $page->type,
        );
        $this->pages->save($translation);
        if ($copyBlocks) {
            $latest = $this->documents->ensureDraft($page->id, $user->id);
            $this->documents->copyDocument($translation->id, $latest->document, $user->id);
        } else {
            $this->documents->ensureDraft($translation->id, $user->id);
        }
        $this->navigation->addToPrimary($site, $translation);
        $this->cache->invalidateSite($site->id);
        $this->contentLocale->remember($target);

        return $this->responses->redirect($basePath . '/admin/pages/' . $translation->id->value . $suffix);
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }
        $deleted = $this->pages->softDeleteTranslationGroup($page);
        if ($deleted < 1) {
            return $this->responses->redirect(
                $basePath . '/admin/pages?locale=' . rawurlencode($page->locale)
                . '&error=' . rawurlencode($this->ui->get($user, 'admin.error.page_delete_failed')),
            );
        }
        $this->cache->invalidateSite($site->id);

        return $this->responses->redirect(
            $basePath . '/admin/pages?locale=' . rawurlencode($page->locale) . '&deleted=1',
        );
    }

    /**
     * @return array{User, Site, string}|ResponseInterface
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
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        return [$user, $site, $basePath];
    }

    /**
     * @return array{User, Site, Page, string}|ResponseInterface
     */
    private function loadPage(ServerRequestInterface $request): array|ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }

        try {
            $pageId = new PageId((string) $request->getAttribute('id', ''));
        } catch (InvalidArgumentException) {
            return $this->responses->html('Seite nicht gefunden.', 404);
        }

        $page = $this->pages->findById($pageId);
        if ($page === null) {
            return $this->responses->html('Seite nicht gefunden.', 404);
        }
        $site = $this->sites->findById($page->siteId);
        if ($site === null) {
            return $this->responses->html('Seite nicht gefunden.', 404);
        }
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        return [$user, $site, $page, $basePath];
    }

    /**
     * @return array<string, Page>
     */
    private function alternatesByLocale(Page $page): array
    {
        $byLocale = [];
        foreach ($this->pages->alternates($page) as $alt) {
            $byLocale[$alt->locale] = $alt;
        }
        $byLocale[$page->locale] = $page;

        return $byLocale;
    }

    private function normalizeRobots(string $robots): string
    {
        $allowed = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];
        return in_array($robots, $allowed, true) ? $robots : 'index,follow';
    }

    private function parseScheduledAt(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $raw);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function uniquePath(SiteId $siteId, string $locale, string $path): string
    {
        if (!$this->pages->pathExists($siteId, $locale, $path)) {
            return $path;
        }
        $base = $path === '/' ? '/seite' : $path;
        for ($i = 2; $i < 100; $i++) {
            $candidate = $base . '-' . $i;
            if (!$this->pages->pathExists($siteId, $locale, $candidate)) {
                return $candidate;
            }
        }

        return $base . '-' . substr(Uuid::v7(), -8);
    }
}
