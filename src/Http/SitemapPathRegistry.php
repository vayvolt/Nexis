<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Site\SiteId;

/**
 * Collects extra sitemap paths from plugins (archives, feeds, landing pages).
 */
final class SitemapPathRegistry implements SitemapArchiveProvider
{
    /** @var list<string> */
    private array $paths = [];

    /** @var list<callable(SiteId): list<string>> */
    private array $contributors = [];

    public function registerPath(string $path): void
    {
        $path = '/' . ltrim(trim($path), '/');
        if ($path === '/' || in_array($path, $this->paths, true)) {
            return;
        }
        $this->paths[] = $path;
    }

    /**
     * @param callable(SiteId): list<string> $contributor
     */
    public function register(callable $contributor): void
    {
        $this->contributors[] = $contributor;
    }

    public function pathsFor(SiteId $siteId): array
    {
        $out = $this->paths;
        foreach ($this->contributors as $contributor) {
            foreach ($contributor($siteId) as $path) {
                if (!is_string($path) || $path === '') {
                    continue;
                }
                $path = '/' . ltrim(trim($path), '/');
                if ($path !== '/' && !in_array($path, $out, true)) {
                    $out[] = $path;
                }
            }
        }

        return array_values($out);
    }
}
