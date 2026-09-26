<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\AccountNavLinks;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Builder\BlockRegistry;
use Nexis\Builder\BlockRenderer;
use Nexis\Builder\BlockPattern;
use Nexis\Builder\DocumentConflictException;
use Nexis\Builder\DocumentService;
use Nexis\Builder\DocumentValidator;
use Nexis\Builder\PatternId;
use Nexis\Builder\PatternRepository;
use Nexis\Builder\PublishService;
use Nexis\Builder\RevisionId;
use Nexis\Builder\RevisionRepository;
use Nexis\Cache\PageCache;
use Nexis\Content\MenuRepository;
use Nexis\Content\Page;
use Nexis\Content\PageEditorialItem;
use Nexis\Content\PageEditorialItemId;
use Nexis\Content\PageEditorialRepository;
use Nexis\Content\PageId;
use Nexis\Content\PagePath;
use Nexis\Content\PageRepository;
use Nexis\Content\PageSeoAnalysis;
use Nexis\Content\PageStatus;
use Nexis\Http\AdminContentLocale;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\SignedUrl;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\I18n\PublicUi;
use Nexis\Media\MediaRepository;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use Nexis\Theme\ThemeService;
use Nexis\Theme\ThemeViewRenderer;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class BuilderAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PageRepository $pages,
        private SitePolicy $policy,
        private DocumentService $documents,
        private PublishService $publish,
        private RevisionRepository $revisions,
        private BlockRegistry $blocks,
        private BlockRenderer $renderer,
        private SignedUrl $signedUrls,
        private MediaRepository $media,
        private ThemeService $themes,
        private ThemeViewRenderer $themeViews,
        private AdminUi $ui,
        private PublicUi $publicUi,
        private LocalePathResolver $paths,
        private MenuRepository $menus,
        private AccountNavLinks $accountNav,
        private AdminContentLocale $contentLocale,
        private PatternRepository $patterns,
        private DocumentValidator $documentValidator,
        private PageCache $cache,
        private PageEditorialRepository $editorial,
        private AuditLogger $audit,
        private Clock $clock,
    ) {
    }

    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->canOpenBuilder($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }
        $this->contentLocale->remember($page->locale);
        $revision = $this->documents->ensureDraft($page->id, $user->id);
        $signedPreview = '';
        try {
            $signedPreview = $basePath . $this->signedUrls->sign(
                '/preview/' . $page->id->value,
                [],
                900,
            );
        } catch (Throwable) {
            $signedPreview = '';
        }

        return $this->responses->html($this->views->render('admin.pages.builder', [
            'user' => $user,
            'site' => $site,
            'page' => $page,
            'locale' => $page->locale,
            'byLocale' => $this->alternatesByLocale($page),
            'revision' => $revision,
            'revisions' => $this->revisions->listForPage($page->id, 20),
            'blockTypes' => $this->blocks->all(),
            'blockCatalog' => $this->blocks->catalog(),
            'patternCatalog' => $this->patternCatalog($site),
            'mediaCatalog' => $this->mediaCatalog($site, $basePath),
            'documentJson' => json_encode($revision->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'savePatternUrl' => $basePath . '/admin/pages/' . $page->id->value . '/save-pattern',
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => '',
            'notice' => '',
            'signedPreview' => $signedPreview,
            'canPublish' => $this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH),
            'canSubmitReview' => $this->policy->can($user, $site, Permission::CONTENT_PAGE_SUBMIT_REVIEW)
                || $this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH),
            'canEditContent' => $this->canEditContent($user, $site),
            'canEditSeo' => $this->canEditSeo($user, $site),
            'title' => $this->ui->get($user, 'admin.builder.title', ['title' => $page->title]),
            'editorialItems' => $this->editorial->listForPage($page->id),
            'seoAnalysis' => PageSeoAnalysis::analyze($page),
            'seoPreviewUrl' => $this->paths->url($site, $page->locale, $page->path, $basePath),
        ], 'admin.layout'));
    }

    public function createEditorial(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }

        $kind = RequestInput::string($request, 'kind');
        if ($kind !== PageEditorialItem::KIND_TASK) {
            $kind = PageEditorialItem::KIND_COMMENT;
        }
        $body = trim(RequestInput::string($request, 'body'));
        $builderUrl = $basePath . '/admin/pages/' . $page->id->value . '/builder';
        if ($body === '') {
            return $this->responses->redirect($builderUrl . '?editorial_error=' . rawurlencode($this->ui->get($user, 'admin.builder.editorial_error_empty')));
        }

        try {
            $now = $this->clock->now();
            $item = new PageEditorialItem(
                new PageEditorialItemId(Uuid::v7()),
                $page->id,
                $kind,
                $body,
                PageEditorialItem::STATUS_OPEN,
                $user->id->value,
                $now,
                $now,
            );
            $this->editorial->save($item);
        } catch (InvalidArgumentException $e) {
            return $this->responses->redirect($builderUrl . '?editorial_error=' . rawurlencode($e->getMessage()));
        }

        $this->audit->log(
            'content.page.editorial.create',
            $site->id,
            $user->id,
            'page_editorial_item',
            $item->id->value,
            ['page_id' => $page->id->value, 'kind' => $kind],
        );

        return $this->responses->redirect($builderUrl . '?editorial=1');
    }

    public function toggleEditorial(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }

        $builderUrl = $basePath . '/admin/pages/' . $page->id->value . '/builder';
        try {
            $itemId = new PageEditorialItemId((string) $request->getAttribute('itemId', ''));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($builderUrl);
        }

        $item = $this->editorial->findById($itemId, $page->id);
        if ($item === null || !$item->isTask) {
            return $this->responses->redirect($builderUrl);
        }

        $now = $this->clock->now();
        if ($item->isDone) {
            $item->reopen($now);
        } else {
            $item->markDone($user->id->value, $now);
        }
        $this->editorial->save($item);

        $this->audit->log(
            'content.page.editorial.resolve',
            $site->id,
            $user->id,
            'page_editorial_item',
            $item->id->value,
            ['page_id' => $page->id->value, 'status' => $item->status],
        );

        return $this->responses->redirect($builderUrl . '?editorial=1#nx-editorial');
    }

    public function deleteEditorial(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }

        $builderUrl = $basePath . '/admin/pages/' . $page->id->value . '/builder';
        try {
            $itemId = new PageEditorialItemId((string) $request->getAttribute('itemId', ''));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($builderUrl);
        }

        $item = $this->editorial->findById($itemId, $page->id);
        if ($item === null) {
            return $this->responses->redirect($builderUrl);
        }

        $canDelete = $item->createdBy === $user->id->value
            || $this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH);
        if (!$canDelete) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        $this->editorial->delete($itemId, $page->id);
        $this->audit->log(
            'content.page.editorial.delete',
            $site->id,
            $user->id,
            'page_editorial_item',
            $itemId->value,
            ['page_id' => $page->id->value, 'kind' => $item->kind],
        );

        return $this->responses->redirect($builderUrl . '?editorial=1#nx-editorial');
    }

    public function savePattern(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }

        $builderUrl = $basePath . '/admin/pages/' . $page->id->value . '/builder';
        $name = trim(RequestInput::string($request, 'name'));
        if ($name === '' || strlen($name) > 190) {
            return $this->responses->redirect($builderUrl . '?pattern_error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_name')));
        }

        $raw = RequestInput::string($request, 'document');
        try {
            $node = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->responses->redirect($builderUrl . '?pattern_error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_document')));
        }
        if (!is_array($node)) {
            return $this->responses->redirect($builderUrl . '?pattern_error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_document')));
        }

        $errors = $this->documentValidator->validate(['schemaVersion' => 1, 'root' => $node]);
        if ($errors !== []) {
            return $this->responses->redirect($builderUrl . '?pattern_error=' . rawurlencode(implode(' ', $errors)));
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $pattern = new BlockPattern(
            new PatternId(Uuid::v7()),
            $site->id,
            $name,
            null,
            $node,
            $user->id->value,
            $now,
            $now,
        );
        $this->patterns->save($pattern);

        return $this->responses->redirect($builderUrl . '?pattern_saved=1');
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        $canEditContent = $this->canEditContent($user, $site);
        $canEditSeo = $this->canEditSeo($user, $site);
        if (!$canEditContent && !$canEditSeo) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }

        if ($canEditContent) {
            $title = trim(RequestInput::string($request, 'title'));
            if ($title === '') {
                return $this->builderError($request, $user, $site, $page, $basePath, $this->ui->get($user, 'admin.error.title_required'), 422);
            }
            $slugInput = RequestInput::string($request, 'slug');
            $path = PagePath::replaceLeaf($page->path, $slugInput !== '' ? $slugInput : $title);
            if ($path !== $page->path) {
                $clash = $this->pages->findByPath($site->id, $page->locale, $path);
                if ($clash !== null && !$clash->id->equals($page->id)) {
                    return $this->builderError($request, $user, $site, $page, $basePath, $this->ui->get($user, 'admin.error.path_taken'), 422);
                }
            }
        } else {
            $title = $page->title;
            $path = $page->path;
        }

        $metaTitle = $page->metaTitle;
        $metaDescription = $page->metaDescription;
        $focusKeyword = $page->focusKeyword;
        $robots = $page->robots;
        if ($canEditSeo) {
            $metaTitle = RequestInput::string($request, 'meta_title') ?: null;
            $metaDescription = RequestInput::string($request, 'meta_description') ?: null;
            $focusKeywordRaw = trim(RequestInput::string($request, 'focus_keyword'));
            if (mb_strlen($focusKeywordRaw) > 120) {
                $focusKeywordRaw = mb_substr($focusKeywordRaw, 0, 120);
            }
            $focusKeyword = $focusKeywordRaw !== '' ? $focusKeywordRaw : null;
            $robots = $this->normalizeRobots(RequestInput::string($request, 'robots', $page->robots));
        }

        $page = new Page(
            $page->id,
            $page->siteId,
            $page->translationGroupId,
            $page->locale,
            PagePath::slugFromPath($path),
            $path,
            $title,
            $page->bodyText,
            $page->status,
            $page->publishedRevisionId,
            $page->publishedSnapshotId,
            $page->searchText,
            $metaTitle,
            $metaDescription,
            $robots,
            $page->scheduledAt,
            $page->scheduledBy,
            $page->type,
            null,
            null,
            null,
            $page->unpublishAt,
            $page->unpublishBy,
            $focusKeyword,
        );
        $this->pages->save($page);
        $this->cache->invalidateSite($site->id);

        if (!$canEditContent) {
            return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?saved=seo');
        }

        $raw = RequestInput::string($request, 'document');
        $expected = RequestInput::string($request, 'document_hash');
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new RuntimeException('Dokument muss ein JSON-Objekt sein.');
            }
            $revision = $this->documents->save(
                $page->id,
                $decoded,
                $expected !== '' ? $expected : null,
                $user->id,
                RequestInput::string($request, 'message') ?: null,
            );
        } catch (DocumentConflictException) {
            return $this->builderError($request, $user, $site, $page, $basePath, $this->ui->get($user, 'admin.error.builder_conflict'), 409);
        } catch (Throwable $e) {
            return $this->builderError($request, $user, $site, $page, $basePath, $e->getMessage(), 422);
        }

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?saved=' . $revision->id->value);
    }

    public function publish(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.publish']), 403);
        }
        try {
            $this->publish->publish($page, null, $user->id, $basePath);
        } catch (Throwable $e) {
            return $this->builderError($request, $user, $site, $page, $basePath, $e->getMessage(), 422);
        }

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?published=1');
    }

    public function submitReview(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        $canSubmit = $this->policy->can($user, $site, Permission::CONTENT_PAGE_SUBMIT_REVIEW)
            || $this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH);
        if (!$canSubmit) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.submit_review']), 403);
        }
        try {
            $this->publish->submitForReview($page, $user->id);
        } catch (Throwable $e) {
            return $this->builderError($request, $user, $site, $page, $basePath, $e->getMessage(), 422);
        }

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?review_submitted=1');
    }

    public function rejectReview(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.publish']), 403);
        }
        try {
            $this->publish->rejectReview($page, $user->id);
        } catch (Throwable $e) {
            return $this->builderError($request, $user, $site, $page, $basePath, $e->getMessage(), 422);
        }

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?review_rejected=1');
    }

    public function unpublish(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.publish']), 403);
        }
        $this->publish->unpublish($page, $user->id);

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?unpublished=1');
    }

    public function schedule(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.publish']), 403);
        }

        $scheduledAt = $this->parseScheduledAt(RequestInput::string($request, 'scheduled_at'));
        if ($scheduledAt === null) {
            return $this->builderError($request, $user, $site, $page, $basePath, $this->ui->get($user, 'admin.error.schedule_invalid'), 422);
        }

        $this->documents->ensureDraft($page->id, $user->id);
        $status = $page->publishedSnapshotId !== null ? PageStatus::Published : PageStatus::Scheduled;
        $updated = new Page(
            $page->id,
            $page->siteId,
            $page->translationGroupId,
            $page->locale,
            $page->slug,
            $page->path,
            $page->title,
            $page->bodyText,
            $status,
            $page->publishedRevisionId,
            $page->publishedSnapshotId,
            $page->searchText,
            $page->metaTitle,
            $page->metaDescription,
            $page->robots,
            $scheduledAt,
            $user->id,
            $page->type,
            null,
            null,
            null,
            $page->unpublishAt,
            $page->unpublishBy,
            $page->focusKeyword,
        );
        $this->pages->save($updated);

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?scheduled=1');
    }

    public function scheduleUnpublish(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.publish']), 403);
        }
        if ($page->publishedSnapshotId === null) {
            return $this->builderError($request, $user, $site, $page, $basePath, $this->ui->get($user, 'admin.error.unpublish_schedule_not_live'), 422);
        }

        $clear = RequestInput::string($request, 'clear_unpublish') === '1';
        $unpublishAt = $clear ? null : $this->parseScheduledAt(RequestInput::string($request, 'unpublish_at'));
        if (!$clear && $unpublishAt === null) {
            return $this->builderError($request, $user, $site, $page, $basePath, $this->ui->get($user, 'admin.error.schedule_invalid'), 422);
        }

        $updated = new Page(
            $page->id,
            $page->siteId,
            $page->translationGroupId,
            $page->locale,
            $page->slug,
            $page->path,
            $page->title,
            $page->bodyText,
            $page->status,
            $page->publishedRevisionId,
            $page->publishedSnapshotId,
            $page->searchText,
            $page->metaTitle,
            $page->metaDescription,
            $page->robots,
            $page->scheduledAt,
            $page->scheduledBy,
            $page->type,
            null,
            null,
            null,
            $unpublishAt,
            $unpublishAt !== null ? $user->id : null,
            $page->focusKeyword,
        );
        $this->pages->save($updated);

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?unpublish_scheduled=1');
    }

    public function revert(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }
        try {
            $revisionId = new RevisionId(RequestInput::string($request, 'revision_id'));
            $this->publish->revert($page, $revisionId, $user->id);
        } catch (DocumentConflictException) {
            return $this->builderError($request, $user, $site, $page, $basePath, 'Konflikt beim Revert (409).', 409);
        } catch (Throwable $e) {
            return $this->builderError($request, $user, $site, $page, $basePath, $e->getMessage(), 422);
        }

        return $this->responses->redirect($basePath . '/admin/pages/' . $page->id->value . '/builder?reverted=1');
    }

    public function preview(ServerRequestInterface $request): ResponseInterface
    {
        $loaded = $this->loadPage($request);
        if ($loaded instanceof ResponseInterface) {
            return $loaded;
        }
        [$user, $site, $page, $basePath] = $loaded;
        $revision = $this->documents->ensureDraft($page->id, $user->id);
        $contentHtml = $this->renderer->renderDocument($revision->document, [
            'basePath' => $basePath,
            'locale' => $page->locale,
            'csrf' => (string) $request->getAttribute('csrf', ''),
        ]);

        try {
            $theme = $this->themes->resolveFor($site, $basePath);
            $template = $this->themes->resolveTemplate($theme['manifest'], 'page');
            $primaryNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'primary', $page->locale, $basePath),
            );
            $footerNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'footer', $page->locale, $basePath),
            );
            $html = $this->themeViews->renderFile($template, [
                'site' => $site,
                'page' => $page,
                'locale' => $page->locale,
                'contentHtml' => $contentHtml,
                'alternates' => [],
                'primaryNav' => $primaryNav,
                'footerNav' => $footerNav,
                'accountNav' => $this->accountNav->forSite($site, $page->locale, $basePath),
                'themeUi' => $this->publicUi->themeChrome($page->locale),
                'layout' => $theme['layout'],
                'basePath' => $basePath,
                'canonical' => $this->paths->url($site, $page->locale, $page->path, $basePath),
                'homeUrl' => $this->paths->url($site, $page->locale, '/', $basePath),
                'cssVariables' => $theme['cssVariables']
                    . ($theme['customCss'] !== '' ? "\n" . $theme['customCss'] : ''),
                'themeCssUrl' => $theme['themeCssUrl'],
                'logoUrl' => $theme['logoUrl'],
                'themeName' => $theme['manifest']->name,
                'isPreview' => true,
            ]);
        } catch (Throwable) {
            return $this->responses->html($this->views->render('admin.pages.preview', [
                'user' => $user,
                'site' => $site,
                'page' => $page,
                'contentHtml' => $contentHtml,
                'basePath' => $basePath,
                'csrf' => (string) $request->getAttribute('csrf', ''),
            ], 'admin.layout'));
        }

        $bar = '<div style="position:sticky;top:0;z-index:9999;background:#1b4d3e;color:#fff;padding:.55rem 1rem;font:14px/1.4 system-ui,sans-serif;display:flex;gap:1rem;flex-wrap:wrap;align-items:center">'
            . '<strong>' . htmlspecialchars($this->ui->get($user, 'admin.builder.preview_draft'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>'
            . '<span style="opacity:.85">' . htmlspecialchars($page->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>'
            . '<a style="color:#fff;margin-left:auto" href="' . htmlspecialchars($basePath . '/admin/pages/' . $page->id->value . '/builder', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . htmlspecialchars($this->ui->get($user, 'admin.pages.back_builder'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</a>'
            . '</div>';
        $html = (string) preg_replace('/<body([^>]*)>/i', '<body$1>' . $bar, $html, 1);

        return $this->responses->html($html)
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Cache-Control', 'no-store');
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

    private function builderError(
        ServerRequestInterface $request,
        User $user,
        Site $site,
        Page $page,
        string $basePath,
        string $error,
        int $status,
    ): ResponseInterface {
        $revision = $this->revisions->latestForPage($page->id) ?? $this->documents->ensureDraft($page->id, $user->id);

        return $this->responses->html($this->views->render('admin.pages.builder', [
            'user' => $user,
            'site' => $site,
            'page' => $page,
            'locale' => $page->locale,
            'byLocale' => $this->alternatesByLocale($page),
            'revision' => $revision,
            'revisions' => $this->revisions->listForPage($page->id, 20),
            'blockTypes' => $this->blocks->all(),
            'blockCatalog' => $this->blocks->catalog(),
            'patternCatalog' => $this->patternCatalog($site),
            'mediaCatalog' => $this->mediaCatalog($site, $basePath),
            'savePatternUrl' => $basePath . '/admin/pages/' . $page->id->value . '/save-pattern',
            'documentJson' => RequestInput::string($request, 'document') !== ''
                ? RequestInput::string($request, 'document')
                : json_encode($revision->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => $error,
            'notice' => '',
            'signedPreview' => '',
            'canPublish' => $this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH),
            'canSubmitReview' => $this->policy->can($user, $site, Permission::CONTENT_PAGE_SUBMIT_REVIEW)
                || $this->policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH),
            'canEditContent' => $this->canEditContent($user, $site),
            'canEditSeo' => $this->canEditSeo($user, $site),
            'title' => $this->ui->get($user, 'admin.builder.title', ['title' => $page->title]),
            'editorialItems' => $this->editorial->listForPage($page->id),
            'seoAnalysis' => PageSeoAnalysis::analyze($page),
            'seoPreviewUrl' => $this->paths->url($site, $page->locale, $page->path, $basePath),
        ], 'admin.layout'), $status);
    }

    private function canEditContent(User $user, Site $site): bool
    {
        return $this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT);
    }

    private function canEditSeo(User $user, Site $site): bool
    {
        return $this->policy->can($user, $site, Permission::CONTENT_PAGE_SEO)
            || $this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT);
    }

    private function canOpenBuilder(User $user, Site $site): bool
    {
        return $this->canEditContent($user, $site) || $this->canEditSeo($user, $site);
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

    /**
     * @return list<array{id: string, name: string, alt: string, url: string, thumbUrl: string, mime: string}>
     */
    /**
     * @return list<array{id: string, name: string, document: array<string, mixed>}>
     */
    private function patternCatalog(Site $site): array
    {
        $out = [];
        foreach ($this->patterns->listBySite($site->id) as $pattern) {
            $out[] = [
                'id' => $pattern->id->value,
                'name' => $pattern->name,
                'document' => $pattern->document,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, name: string, alt: string, url: string, thumbUrl: string, mime: string}>
     */
    private function mediaCatalog(Site $site, string $basePath): array
    {
        $out = [];
        foreach ($this->media->listBySite($site->id) as $asset) {
            $url = $basePath . '/media/' . $asset->id->value;
            $out[] = [
                'id' => $asset->id->value,
                'name' => $asset->originalName,
                'alt' => $asset->displayAlt(),
                'url' => $url,
                'thumbUrl' => $asset->isImage() && $this->media->hasVariant($asset->id, 'thumb')
                    ? $url . '/thumb'
                    : $url,
                'mime' => $asset->mime,
            ];
        }

        return $out;
    }

    private function normalizeRobots(string $robots): string
    {
        $allowed = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];
        $robots = strtolower(trim(str_replace(' ', '', $robots)));
        if (!in_array($robots, $allowed, true)) {
            return 'index,follow';
        }

        return $robots;
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
}
