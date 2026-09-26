<?php

declare(strict_types=1);

namespace Nexis\Site;

final class LocalePathResolver
{
    public function resolve(Site $site, string $requestPath, ?string $host = null): ?ResolvedPath
    {
        $normalized = '/' . trim($requestPath, '/');
        if ($normalized === '/') {
            $segments = [];
        } else {
            $segments = explode('/', trim($normalized, '/'));
        }

        return match ($site->localeUrlStrategy) {
            LocaleUrlStrategy::None => new ResolvedPath(
                $site->defaultLocale,
                $this->pagePath($segments),
            ),
            LocaleUrlStrategy::Prefix => $this->resolvePrefixed($site, $segments),
            LocaleUrlStrategy::Domain => $this->resolveDomain($site, $segments, $host),
        };
    }

    public function url(Site $site, string $locale, string $pagePath, string $basePath = '', ?string $scheme = null): string
    {
        $pagePath = '/' . trim($pagePath, '/');
        if ($pagePath === '/') {
            $pagePath = '';
        }

        if ($site->localeUrlStrategy === LocaleUrlStrategy::Domain) {
            $path = $pagePath === '' ? '/' : $pagePath;
            $relative = rtrim($basePath, '/') . ($path === '/' ? '' : $path);
            if ($relative === '') {
                $relative = '/';
            }
            $host = $this->hostForLocale($site, $locale);
            if ($host !== null) {
                $useScheme = ($scheme !== null && $scheme !== '') ? $scheme : 'https';

                return $useScheme . '://' . $host . ($relative === '/' ? '/' : $relative);
            }

            return $relative;
        }

        $prefix = '';
        if ($site->localeUrlStrategy !== LocaleUrlStrategy::None) {
            $siteLocale = $site->locale($locale) ?? $site->defaultSiteLocale();
            if (is_string($siteLocale->urlPrefix) && $siteLocale->urlPrefix !== '') {
                $prefix = '/' . $siteLocale->urlPrefix;
            }
        }

        $path = $prefix . $pagePath;
        if ($path === '') {
            $path = '/';
        }

        return rtrim($basePath, '/') . $path;
    }

    public function hostForLocale(Site $site, string $locale): ?string
    {
        foreach ($site->domains as $domain) {
            if (($domain['locale'] ?? null) === $locale && is_string($domain['host']) && $domain['host'] !== '') {
                return $domain['host'];
            }
        }

        return null;
    }

    /**
     * @param list<string> $segments
     */
    private function resolveDomain(Site $site, array $segments, ?string $host): ResolvedPath
    {
        $locale = $site->defaultLocale;
        if (is_string($host) && $host !== '') {
            foreach ($site->domains as $domain) {
                if (strcasecmp($domain['host'], $host) === 0 && $domain['locale'] !== null && $domain['locale'] !== '') {
                    $locale = $domain['locale'];
                    break;
                }
            }
        }

        return new ResolvedPath($locale, $this->pagePath($segments));
    }

    /**
     * @param list<string> $segments
     */
    private function resolvePrefixed(Site $site, array $segments): ?ResolvedPath
    {
        $first = $segments[0] ?? null;
        foreach ($site->locales as $locale) {
            if (!$locale->enabled || $locale->urlPrefix === null || $locale->urlPrefix === '') {
                continue;
            }
            if ($first === $locale->urlPrefix) {
                return new ResolvedPath($locale->locale, $this->pagePath(array_slice($segments, 1)));
            }
        }

        $default = $site->defaultSiteLocale();
        if ($default->urlPrefix === null || $default->urlPrefix === '') {
            return new ResolvedPath($default->locale, $this->pagePath($segments));
        }

        if ($segments === []) {
            return new ResolvedPath($default->locale, '/');
        }

        return null;
    }

    /**
     * @param list<string> $segments
     */
    private function pagePath(array $segments): string
    {
        if ($segments === []) {
            return '/';
        }

        return '/' . implode('/', $segments);
    }
}
