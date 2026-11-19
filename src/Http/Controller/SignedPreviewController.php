<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\AccountNavLinks;
use Nexis\Builder\BlockRenderer;
use Nexis\Builder\DocumentService;
use Nexis\Content\MenuRepository;
use Nexis\Content\PageId;
use Nexis\Content\PageRepository;
use Nexis\Http\ResponseFactory;
use Nexis\Http\SignedUrl;
use Nexis\I18n\AdminUi;
use Nexis\I18n\PublicUi;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeService;
use Nexis\Theme\ThemeViewRenderer;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class SignedPreviewController
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PageRepository $pages,
        private DocumentService $documents,
        private BlockRenderer $renderer,
        private ThemeService $themes,
        private ThemeViewRenderer $themeViews,
        private LocalePathResolver $paths,
        private SignedUrl $signedUrls,
        private MenuRepository $menus,
        private AccountNavLinks $accountNav,
        private AdminUi $ui,
        private PublicUi $publicUi,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $path = '/preview/' . (string) $request->getAttribute('id', '');
        /** @var array<string, string> $query */
        $query = [];
        foreach ($request->getQueryParams() as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $query[$key] = $value;
            }
        }

        try {
            $this->signedUrls->assertValid($path, $query);
            $pageId = new PageId((string) $request->getAttribute('id', ''));
        } catch (RuntimeException | InvalidArgumentException) {
            return $this->responses->html($this->ui->getLocale('de', 'admin.preview.invalid'), 403);
        }

        $page = $this->pages->findById($pageId);
        $site = $this->sites->installed();
        if ($page === null || $site === null || !$page->siteId->equals($site->id)) {
            return $this->responses->html($this->ui->getLocale('de', 'admin.preview.page_missing'), 404);
        }

        $revision = $this->documents->ensureDraft($page->id, null);
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
                'contentHtml' => '<p class="bk-fallback">' . htmlspecialchars(
                    $this->ui->getLocale($page->locale, 'admin.builder.preview_draft'),
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8',
                ) . '</p>' . $contentHtml,
                'alternates' => [],
                'primaryNav' => $primaryNav,
                'footerNav' => $footerNav,
                'accountNav' => $this->accountNav->forSite($site, $page->locale, $basePath),
                'themeUi' => $this->publicUi->themeChrome($page->locale),
                'layout' => $theme['layout'],
                'basePath' => $basePath,
                'canonical' => $this->paths->url($site, $page->locale, $page->path, $basePath),
                'homeUrl' => $this->paths->url($site, $page->locale, '/', $basePath),
                'cssVariables' => $theme['cssVariables'],
                'themeCssUrl' => $theme['themeCssUrl'],
                'logoUrl' => $theme['logoUrl'],
                'themeName' => $theme['manifest']->name,
            ]);
        } catch (Throwable) {
            return $this->responses->html($this->ui->getLocale($page->locale, 'public.not_found.theme'), 500);
        }

        return $this->responses->html($html)
            ->withHeader('X-Robots-Tag', 'noindex, nofollow')
            ->withHeader('Cache-Control', 'no-store');
    }
}
