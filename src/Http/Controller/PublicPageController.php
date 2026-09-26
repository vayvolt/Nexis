<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\AccountNavLinks;
use Nexis\Auth\AuthSettings;
use Nexis\Auth\User;
use Nexis\Builder\BlockRenderer;
use Nexis\Builder\RevisionRepository;
use Nexis\Builder\SnapshotRepository;
use Nexis\Cache\DynamicPageTokens;
use Nexis\Cache\PageCache;
use Nexis\Cache\PageCacheBypassRegistry;
use Nexis\Content\MenuRepository;
use Nexis\Content\PageRepository;
use Nexis\Content\PublishMetaHtml;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PublicNotFound;
use Nexis\Http\PublicErrorRenderer;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\Router;
use Nexis\I18n\PublicUi;
use Nexis\Seo\StructuredDataBuilder;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeService;
use Nexis\Theme\ThemeViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use PDOException;
use Throwable;

final class PublicPageController
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PageRepository $pages,
        private SnapshotRepository $snapshots,
        private RevisionRepository $revisions,
        private BlockRenderer $renderer,
        private LocalePathResolver $paths,
        private ThemeService $themes,
        private ThemeViewRenderer $themeViews,
        private PageCache $cache,
        private MenuRepository $menus,
        private PdoSiteSettingsRepository $settings,
        private EventDispatcher $events,
        private AccountNavLinks $accountNav,
        private AuthSettings $authSettings,
        private PublicUi $ui,
        private PageCacheBypassRegistry $pageCacheBypass,
        private StructuredDataBuilder $structuredData,
        private PublicErrorRenderer $errors,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $requestPath = Router::normalizePath((string) $request->getAttribute('path', '/'));

        try {
            $site = $this->sites->installed();
        } catch (PDOException) {
            $site = null;
        }

        if (!$site instanceof Site) {
            return $this->notFound($request, $this->ui->get('de', 'public.not_found.no_site'));
        }

        $defaultLocale = $site->defaultSiteLocale();
        if (
            $requestPath === '/'
            && $site->localeUrlStrategy === LocaleUrlStrategy::Prefix
            && is_string($defaultLocale->urlPrefix)
            && $defaultLocale->urlPrefix !== ''
        ) {
            return $this->responses->redirect($basePath . '/' . $defaultLocale->urlPrefix);
        }

        $host = $request->getUri()->getHost();
        $resolved = $this->paths->resolve(
            $site,
            $requestPath,
            $site->localeUrlStrategy === LocaleUrlStrategy::Domain ? $host : null,
        );
        if ($resolved === null) {
            return $this->notFound(
                $request,
                $this->ui->get($site->defaultLocale, 'public.not_found.page'),
                $site,
                $basePath,
                $site->defaultLocale,
            );
        }

        $page = $this->pages->findByPath($site->id, $resolved->locale, $resolved->pagePath);
        if ($page === null || !$page->isPublished) {
            return $this->notFound(
                $request,
                $this->ui->get($resolved->locale, 'public.not_found.page'),
                $site,
                $basePath,
                $resolved->locale,
            );
        }

        $formOk = RequestInput::query($request, 'form') === 'ok';
        $user = $request->getAttribute('user');
        $loggedIn = $user instanceof User;
        $scheme = $request->getUri()->getScheme();
        $switcherUnpublished = (string) $this->settings->get($site->id, 'i18n.switcher_unpublished', 'hide');
        if ($switcherUnpublished !== 'label') {
            $switcherUnpublished = 'hide';
        }
        $registrationOn = $this->authSettings->registrationEnabled($site->id) ? '1' : '0';
        $snapshotId = $page->publishedSnapshotId;
        $snapshotHash = $snapshotId !== null ? $snapshotId->value : 'none';
        $cacheKey = $this->cache->key(
            $resolved->locale,
            $resolved->pagePath,
            $snapshotHash . '|' . $basePath . '|' . $switcherUnpublished . '|' . $scheme . '|reg=' . $registrationOn,
        );
        if (!$formOk && !$loggedIn && !$this->pageCacheBypass->shouldBypass($request)) {
            $cached = $this->cache->get($site->id, $cacheKey);
            if (is_string($cached)) {
                $dynamic = DynamicPageTokens::isDynamic($cached);
                $html = DynamicPageTokens::hydrate($cached, (string) $request->getAttribute('csrf', ''));

                return $this->responses->html($html)
                    ->withHeader('X-Cache', 'HIT')
                    ->withHeader('Cache-Control', $dynamic ? 'private, no-store' : 'public, max-age=60');
            }
        }

        $renderContext = [
            'basePath' => $basePath,
            'locale' => $resolved->locale,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'formOk' => $formOk,
            'cacheSafe' => true,
        ];
        $document = [];
        $contentHtml = '';
        if ($page->publishedSnapshotId !== null) {
            $snapshot = $this->snapshots->findById($page->publishedSnapshotId);
            if ($snapshot !== null) {
                $document = $snapshot->payload;
                $contentHtml = $this->renderer->renderDocument($document, $renderContext);
            }
        }
        // Fallback: Seite als published markiert, Snapshot aber verloren (z. B. Metadaten-Save).
        if ($contentHtml === '') {
            $latest = $this->revisions->latestForPage($page->id);
            if ($latest !== null) {
                $document = $latest->document;
                $contentHtml = $this->renderer->renderDocument($document, $renderContext);
            }
        }
        if ($contentHtml === '' && is_string($page->bodyText) && $page->bodyText !== '') {
            $contentHtml = '<div class="bk-legacy">' . nl2br(htmlspecialchars($page->bodyText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>';
        }

        $alternatesByLocale = [];
        foreach ($this->pages->alternates($page) as $alternate) {
            $published = $alternate->isPublished && $alternate->publishedSnapshotId !== null;
            if (!$published && $switcherUnpublished === 'hide') {
                continue;
            }
            $hreflang = ($site->locale($alternate->locale) ?? $site->defaultSiteLocale())->hreflang;
            $alternatesByLocale[$alternate->locale] = [
                'locale' => $alternate->locale,
                'url' => $published
                    ? $this->paths->url($site, $alternate->locale, $alternate->path, $basePath, $scheme)
                    : '',
                'hreflang' => $hreflang,
                'ogLocale' => $this->ogLocale($hreflang, $alternate->locale),
                'published' => $published,
                'unavailableLabel' => $published ? null : $this->ui->get($resolved->locale, 'theme.locale.unavailable'),
            ];
        }
        $alternates = [];
        foreach ($site->enabledLocales() as $loc) {
            if (isset($alternatesByLocale[$loc->locale])) {
                $alternates[] = $alternatesByLocale[$loc->locale];
                unset($alternatesByLocale[$loc->locale]);
            }
        }
        foreach ($alternatesByLocale as $row) {
            $alternates[] = $row;
        }

        $this->ui->rememberLocale($resolved->locale);

        $pageHreflang = ($site->locale($resolved->locale) ?? $site->defaultSiteLocale())->hreflang;
        $themeUi = $this->ui->themeChrome($resolved->locale);

        try {
            $theme = $this->themes->resolveFor($site, $basePath);
            $template = $this->themes->resolveTemplate($theme['manifest'], 'page');
            $primaryNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'primary', $resolved->locale, $basePath),
            );
            $footerNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'footer', $resolved->locale, $basePath),
            );
            $canonical = $this->paths->url($site, $page->locale, $page->path, $basePath, $scheme);
            $siteOrigin = $this->requestOrigin($request);
            $jsonLd = $this->structuredData->toScriptTag(
                $this->structuredData->build(
                    $page,
                    $site,
                    $canonical,
                    $document,
                    $theme['logoUrl'] !== '' ? $theme['logoUrl'] : null,
                    $basePath,
                    false,
                    $siteOrigin,
                ),
            );
            $html = $this->themeViews->renderFile($template, [
                'site' => $site,
                'page' => $page,
                'locale' => $resolved->locale,
                'ogLocale' => $this->ogLocale($pageHreflang, $resolved->locale),
                'contentHtml' => $contentHtml,
                'postMetaHtml' => $page->isPost
                    ? PublishMetaHtml::renderHtml(
                        $page,
                        $resolved->locale,
                        fn (string $name): string => $this->ui->get($resolved->locale, 'public.blog.by', ['name' => $name]),
                    )
                    : '',
                'alternates' => $alternates,
                'primaryNav' => $primaryNav,
                'footerNav' => $footerNav,
                'accountNav' => $this->accountNav->forSite(
                    $site,
                    $resolved->locale,
                    $basePath,
                    $user instanceof User ? $user : null,
                ),
                'themeUi' => $themeUi,
                'layout' => $theme['layout'],
                'basePath' => $basePath,
                'canonical' => $canonical,
                'homeUrl' => $this->paths->url($site, $resolved->locale, '/', $basePath, $scheme),
                'cssVariables' => $theme['cssVariables']
                    . ($theme['customCss'] !== '' ? "\n" . $theme['customCss'] : ''),
                'themeCssUrl' => $theme['themeCssUrl'],
                'logoUrl' => $theme['logoUrl'],
                'themeName' => $theme['manifest']->name,
                'jsonLdScript' => $jsonLd,
            ]);
        } catch (Throwable) {
            return $this->notFound(
                $request,
                $this->ui->get($resolved->locale, 'public.not_found.theme'),
                $site,
                $basePath,
                $resolved->locale,
            );
        }

        if (!$formOk && !$loggedIn && !$this->pageCacheBypass->shouldBypass($request)) {
            $this->cache->put($site->id, $cacheKey, $html);
        }

        $dynamic = DynamicPageTokens::isDynamic($html);
        $html = DynamicPageTokens::hydrate($html, (string) $request->getAttribute('csrf', ''));

        return $this->responses->html($html)
            ->withHeader('X-Cache', 'MISS')
            ->withHeader('Cache-Control', $dynamic ? 'private, no-store' : 'public, max-age=60');
    }

    private function notFound(
        ServerRequestInterface $request,
        string $message,
        ?Site $site = null,
        string $basePath = '',
        ?string $locale = null,
    ): ResponseInterface {
        if ($site instanceof Site) {
            $this->logNotFound($request, $site);
        }

        return $this->errors->notFound($request, $message, $site, $locale);
    }

    private function logNotFound(ServerRequestInterface $request, Site $site): void
    {
        $path = Router::normalizePath((string) $request->getAttribute('path', '/'));
        $locale = $site->defaultLocale;
        $resolved = $this->paths->resolve(
            $site,
            $path,
            $site->localeUrlStrategy === LocaleUrlStrategy::Domain ? $request->getUri()->getHost() : null,
        );
        if ($resolved !== null) {
            $locale = $resolved->locale;
            $path = $resolved->pagePath === '/' ? $path : $resolved->pagePath;
        } elseif (preg_match('#^/([a-z]{2}(?:-[A-Z]{2})?)(/|$)#', $path, $m) === 1) {
            $locale = $m[1];
        }

        $referer = $request->getHeaderLine('Referer');
        $ua = $request->getHeaderLine('User-Agent');
        try {
            $this->events->dispatch(new PublicNotFound(
                $site->id,
                $path,
                $locale,
                strtoupper($request->getMethod()),
                $referer !== '' ? $referer : null,
                $ua !== '' ? $ua : null,
            ));
        } catch (Throwable) {
        }
    }

    private function ogLocale(string $hreflang, string $locale): string
    {
        $value = str_replace('-', '_', trim($hreflang !== '' ? $hreflang : $locale));
        if ($value === '') {
            return 'und';
        }
        if (!str_contains($value, '_') && preg_match('/^[a-z]{2}$/i', $value) === 1) {
            return strtolower($value) . '_' . strtoupper($value);
        }

        if (preg_match('/^([a-z]{2})_([a-z]{2})$/i', $value, $m) === 1) {
            return strtolower($m[1]) . '_' . strtoupper($m[2]);
        }

        return $value;
    }

    private function requestOrigin(ServerRequestInterface $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme() !== '' ? $uri->getScheme() : 'http';
        $host = $uri->getHost();
        if ($host === '') {
            return '';
        }
        $origin = $scheme . '://' . $host;
        $port = $uri->getPort();
        if ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $origin .= ':' . $port;
        }

        return $origin;
    }
}
