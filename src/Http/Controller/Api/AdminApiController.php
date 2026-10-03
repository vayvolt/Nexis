<?php

declare(strict_types=1);

namespace Nexis\Http\Controller\Api;

use Nexis\Api\ApiGuard;
use Nexis\Api\ApiSerializer;
use Nexis\Api\ApiToken;
use Nexis\Api\ApiTokenStore;
use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\User;
use Nexis\Builder\BlockRegistry;
use Nexis\Builder\DocumentConflictException;
use Nexis\Builder\DocumentService;
use Nexis\Builder\PublishService;
use Nexis\Builder\RevisionId;
use Nexis\Builder\RevisionRepository;
use Nexis\Cache\PageCache;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageNavigationSync;
use Nexis\Content\PagePath;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PluginToggled;
use Nexis\Http\RequestInput;
use Nexis\Media\MediaAsset;
use Nexis\Media\MediaFolderId;
use Nexis\Media\MediaLibrary;
use Nexis\Media\MediaRepository;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginDiscovery;
use Nexis\Plugin\PluginInstallStatus;
use Nexis\Plugin\PluginManifest;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use Nexis\Theme\ThemeCatalog;
use Nexis\Theme\ThemeService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Admin API (`/api/v1/admin`). Session- or token-authenticated by
 * ApiTokenAuthMiddleware; per-endpoint permissions honour token scopes.
 * Write endpoints take a JSON body and accept declared fields only, except
 * `POST /media`, which accepts `multipart/form-data` like the admin UI.
 */
final class AdminApiController
{
    /** Same set as the builder SEO panel. */
    private const ROBOTS = ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];

    /** `If-Match: *` writes the first revision of a page that has none yet. */
    private const IF_MATCH_ANY = '*';

    /**
     * Theme tokens the branding form of `/admin/theme` writes; the select
     * tokens accept a fixed value list, the rest are free text.
     *
     * @var array<string, list<string>|null>
     */
    private const THEME_TOKENS = [
        'layout.nav.position' => ['right', 'left', 'below'],
        'layout.footer.position' => ['split', 'center', 'stack', 'reverse'],
        'layout.pageTitle' => ['show', 'hide'],
        'layout.footer.showSiteName' => ['true', 'false'],
        'layout.footer.showThemeName' => ['true', 'false'],
        'brand.showSiteName' => ['true', 'false'],
        'brand.logoInNav' => ['true', 'false'],
        'color.brand.primary' => null,
        'color.brand.accent' => null,
        'color.surface' => null,
        'color.text' => null,
        'brand.logoUrl' => null,
        'font.sans' => null,
        'layout.max' => null,
    ];

    public function __construct(
        private ApiGuard $guard,
        private SiteRepository $sites,
        private PageRepository $pages,
        private RevisionRepository $revisions,
        private MediaRepository $media,
        private MediaLibrary $mediaLibrary,
        private ApiTokenStore $tokens,
        private DocumentService $documents,
        private PublishService $publish,
        private PageNavigationSync $navigation,
        private PageCache $cache,
        private AuditLogger $audit,
        private PluginCatalog $plugins,
        private PluginDiscovery $pluginDiscovery,
        private ThemeService $themes,
        private ThemeCatalog $themeCatalog,
        private BlockRegistry $blocks,
        private EventDispatcher $events,
    ) {
    }

    public function siteShow(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;

        return $this->guard->ok($request, ApiSerializer::site($site));
    }

    /**
     * Partial update of the basics behind `/admin/settings` → SiteRepository::updateBasics.
     * Locales keep their own admin routes.
     */
    public function siteUpdate(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::SETTINGS_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $rejected = $this->assertFields($request, $body, [
            'name' => false,
            'primaryDomain' => false,
            'defaultLocale' => false,
            'localeUrlStrategy' => false,
        ]);
        if ($rejected !== null) {
            return $rejected;
        }

        $name = $this->text($body, 'name', $site->name);
        $domain = $this->text($body, 'primaryDomain', $site->primaryDomain);
        if ($name === '' || $domain === '') {
            return $this->guard->invalid($request, 'api.error.site_name_domain');
        }
        $defaultLocale = $this->text($body, 'defaultLocale', $site->defaultLocale);
        if ($site->locale($defaultLocale) === null) {
            return $this->guard->invalid($request, 'api.error.locale_unknown');
        }
        $strategy = LocaleUrlStrategy::tryFrom($this->text($body, 'localeUrlStrategy', $site->localeUrlStrategy->value));
        if ($strategy === null) {
            return $this->guard->invalid($request, 'api.error.locale_strategy_unknown', [
                'allowed' => array_column(LocaleUrlStrategy::cases(), 'value'),
            ]);
        }

        $this->sites->updateBasics($site->id, $name, $domain, $defaultLocale, $strategy);
        $this->cache->invalidateSite($site->id);

        return $this->guard->ok($request, ApiSerializer::site($this->sites->findById($site->id) ?? $site));
    }

    public function pagesIndex(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermissionAny($request, $site, [
            Permission::CONTENT_PAGE_EDIT,
            Permission::CONTENT_PAGE_SEO,
        ]);
        if ($denied !== null) {
            return $denied;
        }

        $locale = $this->locale($request, $site);
        if ($locale === null) {
            return $this->guard->invalid($request, 'api.error.locale_unknown');
        }
        $type = trim(RequestInput::query($request, 'type'));
        $pages = $type !== ''
            ? $this->pages->listBySiteAndType($site->id, $locale, $type)
            : $this->pages->listBySite($site->id, $locale);

        return $this->guard->okList(
            $request,
            array_map(static fn (Page $page): array => ApiSerializer::adminPage($page), $pages),
            ['locale' => $locale, 'type' => $type === '' ? null : $type, 'count' => count($pages)],
        );
    }

    /**
     * Creates a draft plus its initial document, like the admin "new page" flow
     * but for the requested locale only; translations follow via the admin UI.
     */
    public function pagesCreate(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_EDIT);
        if ($denied !== null) {
            return $denied;
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $rejected = $this->assertFields($request, $body, [
            'locale' => false,
            'title' => false,
            'slug' => false,
            'path' => false,
            'type' => false,
        ]);
        if ($rejected !== null) {
            return $rejected;
        }

        $locale = $this->text($body, 'locale', $site->defaultSiteLocale()->locale);
        if ($site->locale($locale) === null) {
            return $this->guard->invalid($request, 'api.error.locale_unknown');
        }
        $title = $this->text($body, 'title', '');
        if ($title === '') {
            return $this->guard->invalid($request, 'api.error.title_required');
        }
        $type = $this->text($body, 'type', PageType::PAGE);
        // Plugins register further types, so the format is checked, not a fixed list.
        if (preg_match('/^[a-z][a-z0-9_-]*$/', $type) !== 1) {
            return $this->guard->invalid($request, 'api.error.page_type_invalid');
        }

        $path = $this->text($body, 'path', '');
        $path = $path !== ''
            ? $this->normalizePath($path)
            : PagePath::fromSlug($this->text($body, 'slug', '') ?: $title);
        if ($this->pages->pathExists($site->id, $locale, $path)) {
            return $this->guard->error($request, 'page.path_taken', 'api.error.path_taken', 409, ['path' => $path]);
        }

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
            type: $type,
        );
        $this->pages->save($page);
        $this->documents->ensureDraft($page->id, $user->id);
        $this->navigation->addToPrimary($site, $page);
        $this->cache->invalidateSite($site->id);

        $basePath = (string) $request->getAttribute('base_path', '');

        return $this->guard->created(
            $request,
            ApiSerializer::adminPage($this->pages->findById($page->id) ?? $page),
            $basePath . '/api/v1/admin/pages/' . $page->id->value,
        );
    }

    /**
     * Sibling page in the translation group, like the locale switch of the
     * admin UI. Idempotent: if the target locale already exists the request
     * answers `200` with that page instead of creating a second one.
     */
    public function pagesTranslationsCreate(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_EDIT);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $rejected = $this->assertFields($request, $body, ['locale' => false, 'copyBlocks' => false], ['copyBlocks']);
        if ($rejected !== null) {
            return $rejected;
        }
        $copyBlocks = $body['copyBlocks'] ?? true;
        if (!is_bool($copyBlocks)) {
            return $this->guard->invalid($request, 'api.error.field_type', ['invalidFields' => ['copyBlocks']]);
        }

        $target = $this->text($body, 'locale', '');
        if ($site->locale($target) === null) {
            return $this->guard->invalid($request, 'api.error.locale_unknown');
        }
        $alternates = $this->pages->alternates($page);
        $existing = array_find($alternates, static fn (Page $alt): bool => $alt->locale === $target);
        if ($existing instanceof Page) {
            return $this->guard->ok($request, ApiSerializer::adminPage($existing));
        }

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

        $basePath = (string) $request->getAttribute('base_path', '');

        return $this->guard->created(
            $request,
            ApiSerializer::adminPage($this->pages->findById($translation->id) ?? $translation),
            $basePath . '/api/v1/admin/pages/' . $translation->id->value,
        );
    }

    public function pagesShow(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermissionAny($request, $site, [
            Permission::CONTENT_PAGE_EDIT,
            Permission::CONTENT_PAGE_SEO,
        ]);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        $alternates = array_map(
            static fn (Page $alternate): array => ApiSerializer::alternate($alternate),
            $this->pages->alternates($page),
        );

        return $this->guard->ok($request, ApiSerializer::adminPage($page) + ['alternates' => $alternates]);
    }

    /**
     * Every locale of the translation group, including the requested page.
     */
    public function pagesAlternates(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermissionAny($request, $site, [
            Permission::CONTENT_PAGE_EDIT,
            Permission::CONTENT_PAGE_SEO,
        ]);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }
        $alternates = $this->pages->alternates($page);

        return $this->guard->okList(
            $request,
            array_map(static fn (Page $alt): array => ApiSerializer::alternate($alt), $alternates),
            ['translationGroupId' => $page->translationGroupId, 'count' => count($alternates)],
        );
    }

    /**
     * Metadata only, split like the builder save: `title`/`slug` need
     * `content.page.edit`, the SEO fields also accept `content.page.seo`.
     * Workflow changes go through the publish endpoints.
     */
    public function pagesUpdate(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermissionAny($request, $site, [
            Permission::CONTENT_PAGE_EDIT,
            Permission::CONTENT_PAGE_SEO,
        ]);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $rejected = $this->assertFields($request, $body, [
            'title' => false,
            'slug' => false,
            'metaTitle' => true,
            'metaDescription' => true,
            'robots' => false,
            'focusKeyword' => true,
        ]);
        if ($rejected !== null) {
            return $rejected;
        }

        $touchesContent = array_key_exists('title', $body) || array_key_exists('slug', $body);
        if ($touchesContent) {
            $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_EDIT);
            if ($denied !== null) {
                return $denied;
            }
        }

        $title = $this->text($body, 'title', $page->title);
        if ($title === '') {
            return $this->guard->invalid($request, 'api.error.title_required');
        }
        $path = $page->path;
        if ($touchesContent) {
            $slug = $this->text($body, 'slug', '');
            $path = PagePath::replaceLeaf($page->path, $slug !== '' ? $slug : $title);
            $clash = $path !== $page->path ? $this->pages->findByPath($site->id, $page->locale, $path) : null;
            if ($clash !== null && !$clash->id->equals($page->id)) {
                return $this->guard->error($request, 'page.path_taken', 'api.error.path_taken', 409, ['path' => $path]);
            }
        }

        $robots = strtolower(str_replace(' ', '', $this->text($body, 'robots', $page->robots)));
        if (!in_array($robots, self::ROBOTS, true)) {
            return $this->guard->invalid($request, 'api.error.robots_invalid', ['allowed' => self::ROBOTS]);
        }
        $focusKeyword = $this->nullableText($body, 'focusKeyword', $page->focusKeyword);
        if ($focusKeyword !== null && mb_strlen($focusKeyword) > 120) {
            $focusKeyword = mb_substr($focusKeyword, 0, 120);
        }

        $updated = new Page(
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
            $this->nullableText($body, 'metaTitle', $page->metaTitle),
            $this->nullableText($body, 'metaDescription', $page->metaDescription),
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
        $this->pages->save($updated);
        $this->cache->invalidateSite($site->id);

        return $this->guard->ok($request, ApiSerializer::adminPage($this->pages->findById($page->id) ?? $updated));
    }

    public function pagesDocument(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_EDIT);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }
        $revision = $this->revisions->latestForPage($page->id);
        if ($revision === null) {
            return $this->guard->notFound($request, 'revision.not_found');
        }

        return $this->guard->ok($request, ApiSerializer::revision($revision));
    }

    /**
     * Stores a new revision. Optimistic concurrency is mandatory: `If-Match`
     * carries the `documentHash` of the revision the client started from.
     */
    public function pagesDocumentSave(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_EDIT);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $document = $body['document'] ?? null;
        if (!is_array($document)) {
            return $this->guard->invalid($request, 'api.error.document_required');
        }

        $expected = self::ifMatch($request);
        if ($expected === '') {
            return $this->guard->error($request, 'page.if_match_required', 'api.error.if_match_required', 428);
        }

        try {
            $revision = $this->documents->save(
                $page->id,
                $document,
                $expected === self::IF_MATCH_ANY ? null : $expected,
                $user->id,
                $this->nullableText($body, 'message', null),
            );
        } catch (DocumentConflictException) {
            return $this->guard->error($request, 'page.conflict', 'api.error.document_conflict', 409, [
                'documentHash' => $this->revisions->latestForPage($page->id)?->documentHash,
            ]);
        } catch (Throwable $e) {
            return $this->guard->invalid($request, 'api.error.document_invalid', ['reason' => $e->getMessage()]);
        }

        return $this->guard->ok($request, ApiSerializer::revision($revision, false));
    }

    /**
     * Publishes the latest revision, or `revisionId` from the body.
     */
    public function pagesPublish(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_PUBLISH);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $rejected = $this->assertFields($request, $body, ['revisionId' => false]);
        if ($rejected !== null) {
            return $rejected;
        }

        $revisionId = $this->text($body, 'revisionId', '');
        try {
            $this->publish->publish(
                $page,
                $revisionId !== '' ? new RevisionId($revisionId) : null,
                $user->id,
                (string) $request->getAttribute('base_path', ''),
            );
        } catch (Throwable $e) {
            return $this->guard->invalid($request, 'api.error.publish_failed', ['reason' => $e->getMessage()]);
        }

        return $this->guard->ok($request, ApiSerializer::adminPage($this->pages->findById($page->id) ?? $page));
    }

    /**
     * Takes the page offline; the draft document stays untouched. Idempotent.
     */
    public function pagesUnpublish(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_PUBLISH);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }
        $this->publish->unpublish($page, $user->id);

        return $this->guard->ok($request, ApiSerializer::adminPage($this->pages->findById($page->id) ?? $page));
    }

    /**
     * Copies an older revision into a new draft revision; the published
     * snapshot stays untouched until the next publish.
     */
    public function pagesRevert(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_PAGE_PUBLISH);
        if ($denied !== null) {
            return $denied;
        }

        $page = $this->findPage($request, $site);
        if ($page === null) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $rejected = $this->assertFields($request, $body, ['revisionId' => false]);
        if ($rejected !== null) {
            return $rejected;
        }
        $revisionId = $this->text($body, 'revisionId', '');
        if ($revisionId === '') {
            return $this->guard->invalid($request, 'api.error.revision_id_required');
        }
        try {
            $source = $this->revisions->findById(new RevisionId($revisionId));
        } catch (\InvalidArgumentException) {
            $source = null;
        }
        if ($source === null || !$source->pageId->equals($page->id)) {
            return $this->guard->notFound($request, 'revision.not_found');
        }

        try {
            $revision = $this->publish->revert($page, $source->id, $user->id);
        } catch (DocumentConflictException) {
            return $this->guard->error($request, 'page.conflict', 'api.error.document_conflict', 409, [
                'documentHash' => $this->revisions->latestForPage($page->id)?->documentHash,
            ]);
        } catch (Throwable $e) {
            return $this->guard->invalid($request, 'api.error.revert_failed', ['reason' => $e->getMessage()]);
        }

        return $this->guard->ok($request, ApiSerializer::revision($revision));
    }

    public function mediaIndex(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_MEDIA_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $basePath = (string) $request->getAttribute('base_path', '');
        $assets = $this->media->listBySite($site->id);

        return $this->guard->okList(
            $request,
            array_map(
                static fn (MediaAsset $asset): array => ApiSerializer::media($asset, $basePath),
                $assets,
            ),
            ['count' => count($assets)],
        );
    }

    /**
     * Multipart upload (`file`, optional `alt_text`, optional `folder_id`), same
     * rules as `/admin/media` → MediaLibrary::store.
     */
    public function mediaCreate(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::CONTENT_MEDIA_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $files = $request->getUploadedFiles();
        $file = $files['file'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return $this->guard->invalid($request, 'api.error.media_file_required');
        }

        $folderId = $this->optionalMediaFolderId($request, $site);
        if ($folderId === false) {
            return $this->guard->invalid($request, 'api.error.media_folder_not_found');
        }

        $alt = RequestInput::string($request, 'alt_text');
        try {
            $asset = $this->mediaLibrary->store($site->id, $file, $alt, $folderId);
            $this->audit->log('media.upload', $site->id, $user->id, 'media', $asset->id->value, [
                'name' => $asset->originalName,
                'mime' => $asset->mime,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->mapMediaStoreFailure($request, $e);
        } catch (Throwable) {
            return $this->guard->invalid($request, 'api.error.media_upload_rejected');
        }

        $basePath = (string) $request->getAttribute('base_path', '');
        $fresh = $this->media->findById($asset->id, $site->id) ?? $asset;

        return $this->guard->created(
            $request,
            ApiSerializer::media($fresh, $basePath),
            $basePath . '/api/v1/admin/media/' . $asset->id->value,
        );
    }

    public function tokensIndex(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::SETTINGS_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $tokens = $this->tokens->listForSite($site->id);

        return $this->guard->okList(
            $request,
            array_map(static fn (ApiToken $token): array => ApiSerializer::token($token), $tokens),
            ['count' => count($tokens)],
        );
    }

    /**
     * Revoking is idempotent and cannot escalate privileges; creating tokens
     * stays in the admin UI (`/admin/api-tokens`).
     */
    public function tokensRevoke(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::SETTINGS_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $id = (string) $request->getAttribute('id', '');
        $token = $this->tokens->find($id, $site->id);
        if ($token === null) {
            return $this->guard->notFound($request, 'token.not_found');
        }
        if ($this->tokens->revoke($token->id, $site->id)) {
            $this->auditRevoke($user, $site, $token->id);
        }

        return $this->guard->ok($request, ApiSerializer::token(
            $this->tokens->find($id, $site->id) ?? $token,
        ));
    }

    /**
     * Discovered packages plus their installation status for this site, like
     * `/admin/plugins`.
     */
    public function pluginsIndex(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::PLUGIN_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $this->plugins->sync($this->pluginDiscovery->discover());
        $rows = $this->plugins->listForSite($site->id);

        return $this->guard->okList(
            $request,
            array_map(static fn (array $row): array => ApiSerializer::plugin($row), $rows),
            ['count' => count($rows)],
        );
    }

    public function pluginsEnable(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pluginToggle($request, PluginInstallStatus::Enabled);
    }

    public function pluginsDisable(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pluginToggle($request, PluginInstallStatus::Disabled);
    }

    public function themeTokensShow(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::THEME_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        return $this->guard->ok(
            $request,
            ApiSerializer::themeTokens(
                $this->themeCatalog->overrides($site->id),
                $this->themeCatalog->customCss($site->id),
            ),
            // Token → allowed values, `null` for free text.
            ['editableTokens' => self::THEME_TOKENS],
        );
    }

    /**
     * Partial update of the branding overrides behind `/admin/theme`: only the
     * sent tokens change, a `null` (or empty) value drops the override, and a
     * value equal to the theme default is not stored at all.
     */
    public function themeTokensUpdate(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::THEME_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $body = $this->guard->json($request);
        if ($body instanceof ResponseInterface) {
            return $body;
        }
        $rejected = $this->assertFields($request, $body, ['tokens' => true, 'customCss' => true], ['tokens']);
        if ($rejected !== null) {
            return $rejected;
        }
        $sent = $body['tokens'] ?? [];
        if (!is_array($sent)) {
            return $this->guard->invalid($request, 'api.error.field_type', ['invalidFields' => ['tokens']]);
        }

        $tokens = $this->themeCatalog->overrides($site->id);
        $defaults = $this->themes->defaultsFor($site);
        foreach ($sent as $key => $value) {
            if (!is_string($key) || !array_key_exists($key, self::THEME_TOKENS)) {
                return $this->guard->invalid($request, 'api.error.theme_token_unknown', [
                    'unknownTokens' => [is_string($key) ? $key : (string) $key],
                    'allowedTokens' => array_keys(self::THEME_TOKENS),
                ]);
            }
            if ($value !== null && !is_string($value)) {
                return $this->guard->invalid($request, 'api.error.field_type', ['invalidFields' => ['tokens.' . $key]]);
            }
            $value = $value === null ? '' : trim($value);
            $allowed = self::THEME_TOKENS[$key];
            if ($allowed !== null && $value !== '' && !in_array($value, $allowed, true)) {
                return $this->guard->invalid($request, 'api.error.theme_token_value', [
                    'token' => $key,
                    'allowed' => $allowed,
                ]);
            }
            if ($value === '' || $value === ($defaults[$key] ?? null)) {
                unset($tokens[$key]);
            } else {
                $tokens[$key] = $value;
            }
        }

        $customCss = $this->themeCatalog->customCss($site->id);
        if (array_key_exists('customCss', $body)) {
            $denied = $this->guard->assertApiPermission($request, $site, Permission::THEME_CUSTOM_CSS);
            if ($denied !== null) {
                return $denied;
            }
            $customCss = (string) $this->nullableText($body, 'customCss', null);
        }

        $this->themeCatalog->saveOverrides($site->id, $tokens, $customCss !== '' ? $customCss : null, $user->id->value);
        $this->cache->invalidateSite($site->id);
        $this->audit->log('theme.branding.update', $site->id, $user->id, 'site', $site->id->value, [
            'keys' => array_keys($tokens),
        ]);

        return $this->guard->ok($request, ApiSerializer::themeTokens(
            $this->themeCatalog->overrides($site->id),
            $this->themeCatalog->customCss($site->id),
        ));
    }

    /**
     * Block registry incl. plugin blocks, so clients can build documents for
     * `PUT /pages/{id}/document`. Needs CMS access only; ApiTokenAuthMiddleware
     * has already checked it.
     */
    public function blocksIndex(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        $catalog = $this->blocks->catalog();

        return $this->guard->okList(
            $request,
            array_map(static fn (array $entry): array => ApiSerializer::block($entry), $catalog),
            ['count' => count($catalog)],
        );
    }

    /**
     * Enable/disable for `vendor/name` from the path, with the same guards as
     * the admin UI: required plugins must be enabled first, and the toggle
     * writes an audit entry plus `PluginToggled` (webhooks).
     */
    private function pluginToggle(
        ServerRequestInterface $request,
        PluginInstallStatus $status,
    ): ResponseInterface {
        $ctx = $this->guard->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site] = $ctx;
        $denied = $this->guard->assertApiPermission($request, $site, Permission::PLUGIN_MANAGE);
        if ($denied !== null) {
            return $denied;
        }

        $key = (string) $request->getAttribute('vendor', '') . '/' . (string) $request->getAttribute('name', '');
        $this->plugins->sync($this->pluginDiscovery->discover());
        if ($this->plugins->findPluginId($key) === null) {
            return $this->guard->notFound($request, 'plugin.not_found');
        }
        if ($status === PluginInstallStatus::Enabled) {
            $missing = $this->missingRequiredPlugins($site, $key);
            if ($missing !== []) {
                return $this->guard->invalid($request, 'api.error.plugin_requires', [
                    'plugin' => $key,
                    'requires' => $missing,
                ]);
            }
        }

        $this->plugins->ensureInstallation($site->id, $key, PluginInstallStatus::Installed);
        $this->plugins->setStatus($site->id, $key, $status);
        $enabled = $status === PluginInstallStatus::Enabled;
        $this->audit->log(
            $enabled ? 'plugin.enable' : 'plugin.disable',
            $site->id,
            $user->id,
            'plugin',
            null,
            ['plugin' => $key],
        );
        $this->events->dispatch(new PluginToggled($site->id, $key, $enabled, $user->id));

        $row = array_find(
            $this->plugins->listForSite($site->id),
            static fn (array $item): bool => ($item['key'] ?? null) === $key,
        );

        return $row === null
            ? $this->guard->notFound($request, 'plugin.not_found')
            : $this->guard->ok($request, ApiSerializer::plugin($row));
    }

    /**
     * @return list<string> Plugin keys the package requires that are not enabled
     */
    private function missingRequiredPlugins(Site $site, string $pluginKey): array
    {
        $manifest = array_find(
            $this->pluginDiscovery->discover(),
            static fn (PluginManifest $item): bool => $item->id === $pluginKey,
        );
        if ($manifest === null || $manifest->requiredPlugins === []) {
            return [];
        }
        $enabled = array_fill_keys($this->plugins->enabledKeys($site->id), true);

        return array_values(array_filter(
            $manifest->requiredPlugins,
            static fn (string $required): bool => !isset($enabled[$required]),
        ));
    }

    private function auditRevoke(User $user, Site $site, string $tokenId): void
    {
        $this->audit->log('api_token.revoke', $site->id, $user->id, 'api_token', $tokenId);
    }

    private function findPage(ServerRequestInterface $request, Site $site): ?Page
    {
        $id = (string) $request->getAttribute('id', '');
        try {
            $page = $this->pages->findById(new PageId($id));
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $page !== null && $page->siteId->equals($site->id) ? $page : null;
    }

    private function locale(ServerRequestInterface $request, Site $site): ?string
    {
        $requested = trim(RequestInput::query($request, 'locale'));
        if ($requested === '') {
            return $site->defaultSiteLocale()->locale;
        }

        return $site->locale($requested)?->locale;
    }

    /**
     * Guards the write bodies: unknown keys and wrong value types are rejected
     * instead of silently ignored, so typos never pass as a no-op update.
     *
     * @param array<string, mixed> $body
     * @param array<string, bool> $allowed Field name → may be `null` to clear the value
     * @param list<string> $ownTypeCheck Fields the caller validates itself (non-string payloads)
     */
    private function assertFields(
        ServerRequestInterface $request,
        array $body,
        array $allowed,
        array $ownTypeCheck = [],
    ): ?ResponseInterface {
        $unknown = array_values(array_diff(array_keys($body), array_keys($allowed)));
        if ($unknown !== []) {
            return $this->guard->invalid($request, 'api.error.unknown_fields', [
                'unknownFields' => $unknown,
                'allowedFields' => array_keys($allowed),
            ]);
        }

        $wrongType = [];
        foreach ($allowed as $field => $nullable) {
            if (!array_key_exists($field, $body) || in_array($field, $ownTypeCheck, true)) {
                continue;
            }
            $value = $body[$field];
            if (!is_string($value) && !($nullable && $value === null)) {
                $wrongType[] = $field;
            }
        }

        return $wrongType === []
            ? null
            : $this->guard->invalid($request, 'api.error.field_type', ['invalidFields' => $wrongType]);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function text(array $body, string $field, string $default): string
    {
        $value = $body[$field] ?? null;

        return is_string($value) ? trim($value) : $default;
    }

    /**
     * Nullable field with PATCH semantics: absent keeps the current value,
     * `null` or `""` clears it.
     *
     * @param array<string, mixed> $body
     */
    private function nullableText(array $body, string $field, ?string $default): ?string
    {
        if (!array_key_exists($field, $body)) {
            return $default;
        }
        $value = $body[$field];

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Slugifies every segment so nested paths stay URL-safe.
     */
    private function normalizePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            $slug = trim(PagePath::fromSlug($segment), '/');
            if ($slug !== '') {
                $segments[] = $slug;
            }
        }

        return $segments === [] ? '/' : '/' . implode('/', $segments);
    }

    private static function ifMatch(ServerRequestInterface $request): string
    {
        $value = trim($request->getHeaderLine('If-Match'));
        if (str_starts_with($value, 'W/')) {
            $value = trim(substr($value, 2));
        }

        return trim($value, '"');
    }

    /**
     * @return MediaFolderId|null|false null = root/unfiled, false = unknown folder
     */
    private function optionalMediaFolderId(ServerRequestInterface $request, Site $site): MediaFolderId|null|false
    {
        $raw = RequestInput::string($request, 'folder_id');
        if ($raw === '') {
            return null;
        }
        try {
            $folderId = new MediaFolderId($raw);
        } catch (\InvalidArgumentException) {
            return false;
        }
        if ($this->media->findFolder($folderId, $site->id) === null) {
            return false;
        }

        return $folderId;
    }

    private function mapMediaStoreFailure(
        ServerRequestInterface $request,
        \InvalidArgumentException $e,
    ): ResponseInterface {
        $messageKey = match ($e->getMessage()) {
            'media.alt_required' => 'api.error.media_alt_required',
            'media.too_large' => 'api.error.media_too_large',
            'media.too_many_pixels' => 'api.error.media_too_many_pixels',
            default => 'api.error.media_upload_rejected',
        };

        return $this->guard->invalid($request, $messageKey);
    }
}
