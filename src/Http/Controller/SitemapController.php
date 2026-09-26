<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Content\Page;
use Nexis\Content\PageRepository;
use Nexis\Http\ResponseFactory;
use Nexis\Http\SitemapArchiveProvider;
use Nexis\I18n\PublicUi;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SitemapController
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PageRepository $pages,
        private LocalePathResolver $paths,
        private SitemapArchiveProvider $archives,
        private PublicUi $ui,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->getRequest($request, 'http.no_site'), 503);
        }

        $basePath = (string) $request->getAttribute('base_path', '');
        $origin = $this->origin($request);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($site->enabledLocales() as $locale) {
            $pages = $this->pages->listPublishedForLocale($site->id, $locale->locale);
            $archivePaths = $this->archives->pathsFor($site->id);
            if ($pages === [] && $archivePaths === []) {
                continue;
            }
            $loc = $origin . $basePath . '/sitemap-' . rawurlencode($locale->locale) . '.xml';
            $xml .= '  <sitemap><loc>' . $this->esc($loc) . '</loc></sitemap>' . "\n";
        }

        $xml .= '</sitemapindex>';

        return $this->responses->xml($xml)
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    public function locale(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->getRequest($request, 'http.no_site'), 503);
        }

        $locale = (string) $request->getAttribute('locale', '');
        if ($site->locale($locale) === null) {
            return $this->responses->html($this->ui->getRequest($request, 'http.locale_not_found'), 404);
        }

        $basePath = (string) $request->getAttribute('base_path', '');
        $scheme = $request->getUri()->getScheme();
        $origin = $this->origin($request);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($this->pages->listPublishedForLocale($site->id, $locale) as $page) {
            if (str_contains($page->robots, 'noindex')) {
                continue;
            }
            $loc = $this->absoluteUrl($site, $page->locale, $page->path, $basePath, $scheme, $origin);
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $this->esc($loc) . '</loc>' . "\n";
            if ($page->updatedAt !== null) {
                $xml .= '    <lastmod>' . $this->esc($page->updatedAt->format('Y-m-d')) . '</lastmod>' . "\n";
            }

            $publishedAlts = array_values(array_filter(
                $this->pages->alternates($page),
                static fn (Page $alt): bool => $alt->isPublished
                    && $alt->publishedSnapshotId !== null
                    && !str_contains($alt->robots, 'noindex'),
            ));

            foreach ($publishedAlts as $alt) {
                $href = $this->absoluteUrl($site, $alt->locale, $alt->path, $basePath, $scheme, $origin);
                $hreflang = ($site->locale($alt->locale) ?? $site->defaultSiteLocale())->hreflang;
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $this->esc($hreflang) . '" href="' . $this->esc($href) . '"/>' . "\n";
            }

            $defaultAlt = array_find(
                $publishedAlts,
                static fn (Page $alt): bool => $alt->locale === $site->defaultLocale,
            );
            if ($defaultAlt instanceof Page) {
                $href = $this->absoluteUrl($site, $defaultAlt->locale, $defaultAlt->path, $basePath, $scheme, $origin);
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $this->esc($href) . '"/>' . "\n";
            }

            $xml .= "  </url>\n";
        }

        foreach ($this->archives->pathsFor($site->id) as $archivePath) {
            $loc = $this->absoluteUrl($site, $locale, $archivePath, $basePath, $scheme, $origin);
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $this->esc($loc) . '</loc>' . "\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $this->responses->xml($xml)
            ->withHeader('Cache-Control', 'public, max-age=300');
    }

    private function origin(ServerRequestInterface $request): string
    {
        $host = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        $port = $request->getUri()->getPort();
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $host .= ':' . $port;
        }

        return $host;
    }

    private function absoluteUrl(
        Site $site,
        string $locale,
        string $path,
        string $basePath,
        string $scheme,
        string $origin,
    ): string {
        $pathOrUrl = $this->paths->url($site, $locale, $path, $basePath, $scheme);

        return preg_match('#^https?://#i', $pathOrUrl) === 1
            ? $pathOrUrl
            : $origin . $pathOrUrl;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
