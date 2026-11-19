<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\PublicUi;
use Nexis\Search\SearchPort;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SearchController
{
    public function __construct(
        private ResponseFactory $responses,
        private ViewRenderer $views,
        private ThemeService $themes,
        private SiteRepository $sites,
        private SearchPort $search,
        private PublicUi $ui,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->sites->installed();
        $basePath = (string) $request->getAttribute('base_path', '');
        if ($site === null) {
            return $this->responses->html($this->ui->get('de', 'public.not_found.no_site'), 503);
        }

        $path = (string) $request->getAttribute('path', '/');
        $locale = $site->defaultLocale;
        foreach ($site->locales as $siteLocale) {
            $prefix = '/' . $siteLocale->urlPrefix;
            if ($siteLocale->urlPrefix !== '' && (str_starts_with($path, $prefix . '/') || $path === $prefix || str_starts_with($path, $prefix . '?'))) {
                $locale = $siteLocale->locale;
                break;
            }
        }
        // /de/search or /search
        if (preg_match('#^/([a-z]{2}(?:-[A-Z]{2})?)/search$#', $path, $m) === 1) {
            $locale = $m[1];
        }

        $query = trim(RequestInput::query($request, 'q'));
        $hits = $query !== '' ? $this->search->search($site->id, $locale, $query) : [];
        $translator = $this->ui->translator($locale);
        $t = static fn (string $key, array $replace = [], ?string $default = null): string => $translator->get($key, $replace, $default);

        $viewData = [
            'site' => $site,
            'locale' => $locale,
            'query' => $query,
            'hits' => $hits,
            'basePath' => $basePath,
            't' => $t,
            'uiLocale' => $translator->locale(),
        ];

        try {
            $theme = $this->themes->resolveFor($site, $basePath);
            $html = $this->views->render('public.search', array_merge($viewData, [
                'cssVariables' => $theme['cssVariables'],
                'themeCssUrl' => $theme['themeCssUrl'],
                'logoUrl' => $theme['logoUrl'],
                'homeUrl' => $basePath . '/' . ($site->locale($locale)?->urlPrefix ?: $locale),
            ]));
        } catch (Throwable) {
            $html = $this->views->render('public.search', array_merge($viewData, [
                'cssVariables' => '',
                'themeCssUrl' => '',
                'logoUrl' => '',
                'homeUrl' => $basePath . '/',
            ]));
        }

        return $this->responses->html($html);
    }
}
