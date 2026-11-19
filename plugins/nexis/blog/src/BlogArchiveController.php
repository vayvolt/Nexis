<?php

declare(strict_types=1);

namespace Nexis\Plugins\Blog;

use Nexis\Auth\AccountNavLinks;
use Nexis\Content\MenuRepository;
use Nexis\Content\PageRepository;
use Nexis\Content\PageType;
use Nexis\Content\PublishMetaHtml;
use Nexis\Http\ResponseFactory;
use Nexis\Http\Router;
use Nexis\I18n\PublicUi;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeService;
use Nexis\Theme\ThemeViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class BlogArchiveController
{
    public function __construct(
        private ResponseFactory $responses,
        private ThemeService $themes,
        private ThemeViewRenderer $themeViews,
        private SiteRepository $sites,
        private PageRepository $pages,
        private MenuRepository $menus,
        private LocalePathResolver $paths,
        private AccountNavLinks $accountNav,
        private PublicUi $ui,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->sites->installed();
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$site instanceof Site) {
            return $this->responses->html($this->ui->get('de', 'public.not_found.no_site'), 503);
        }

        $path = Router::normalizePath((string) $request->getAttribute('path', '/'));
        $locale = $this->resolveLocale($site, $path, $request);
        if ($site->locale($locale) === null) {
            $locale = $site->defaultLocale;
        }

        $scheme = $request->getUri()->getScheme();
        $posts = $this->pages->listPublishedByTypeForLocale($site->id, $locale, PageType::POST);
        $contentHtml = $this->renderArchiveHtml($posts, $site, $locale, $basePath);
        $title = $this->ui->get($locale, 'public.blog.title');

        $page = new ArchivePageView(
            $title,
            $title . ' – ' . $site->name,
            $this->ui->get($locale, 'public.blog.description'),
        );

        $alternates = [];
        foreach ($site->enabledLocales() as $loc) {
            $alternates[] = [
                'locale' => $loc->locale,
                'url' => $this->paths->url($site, $loc->locale, BlogPaths::archivePath(), $basePath, $scheme),
                'hreflang' => $loc->hreflang,
                'published' => true,
            ];
        }

        $this->ui->rememberLocale($locale);

        try {
            $theme = $this->themes->resolveFor($site, $basePath);
            $template = $this->themes->resolveTemplate($theme['manifest'], 'page');
            $primaryNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'primary', $locale, $basePath),
            );
            $footerNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'footer', $locale, $basePath),
            );
            $html = $this->themeViews->renderFile($template, [
                'site' => $site,
                'page' => $page,
                'locale' => $locale,
                'contentHtml' => $contentHtml,
                'alternates' => $alternates,
                'primaryNav' => $primaryNav,
                'footerNav' => $footerNav,
                'accountNav' => $this->accountNav->forSite($site, $locale, $basePath),
                'themeUi' => $this->ui->themeChrome($locale),
                'layout' => $theme['layout'],
                'basePath' => $basePath,
                'canonical' => $this->paths->url($site, $locale, BlogPaths::archivePath(), $basePath, $scheme),
                'homeUrl' => $this->paths->url($site, $locale, '/', $basePath, $scheme),
                'cssVariables' => $theme['cssVariables']
                    . ($theme['customCss'] !== '' ? "\n" . $theme['customCss'] : ''),
                'themeCssUrl' => $theme['themeCssUrl'],
                'logoUrl' => $theme['logoUrl'],
                'themeName' => $theme['manifest']->name,
            ]);
        } catch (Throwable) {
            return $this->responses->html($this->ui->get($locale, 'public.not_found.theme'), 500);
        }

        return $this->responses->html($html);
    }

    /**
     * @param list<\Nexis\Content\Page> $posts
     */
    private function renderArchiveHtml(array $posts, Site $site, string $locale, string $basePath): string
    {
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<section class="nx-blog-archive">';
        $html .= '<h1 class="theme-page-title">' . $e($this->ui->get($locale, 'public.blog.title')) . '</h1>';
        if ($posts === []) {
            $html .= '<p class="nx-blog-archive__empty">' . $e($this->ui->get($locale, 'public.blog.empty')) . '</p>';
        } else {
            $html .= '<ul class="nx-blog-archive__list">';
            foreach ($posts as $post) {
                $url = $this->paths->url($site, $post->locale, $post->path, $basePath);
                $html .= '<li class="nx-blog-archive__item">';
                $html .= '<a href="' . $e($url) . '"><strong>' . $e($post->title) . '</strong></a>';
                $html .= PublishMetaHtml::renderHtml(
                    $post,
                    $locale,
                    fn (string $name): string => $this->ui->get($locale, 'public.blog.by', ['name' => $name]),
                );
                $excerpt = $post->documentDescription;
                if ($excerpt !== '') {
                    $html .= '<p>' . $e($excerpt) . '</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $html .= '</section>';

        return $html;
    }

    private function resolveLocale(Site $site, string $path, ServerRequestInterface $request): string
    {
        $attr = $request->getAttribute('locale');
        if (is_string($attr) && $attr !== '' && $site->locale($attr) !== null) {
            return $attr;
        }

        if (preg_match('#^/([a-z]{2}(?:-[A-Z]{2})?)/blog$#', $path, $m) === 1) {
            return $m[1];
        }

        return $site->defaultLocale;
    }
}
