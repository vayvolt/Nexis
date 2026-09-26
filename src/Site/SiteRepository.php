<?php

declare(strict_types=1);

namespace Nexis\Site;

interface SiteRepository
{
    public function findById(SiteId $id): ?Site;

    public function findByHost(string $host): ?Site;

    /**
     * Die eine installierte Website. Eine Installation verwaltet genau eine Site.
     */
    public function installed(): ?Site;

    /**
     * @return list<Site>
     */
    public function all(): array;

    /**
     * @param list<SiteId> $ids
     * @return list<Site>
     */
    public function findByIds(array $ids): array;

    public function updateBasics(
        SiteId $id,
        string $name,
        string $primaryDomain,
        string $defaultLocale,
        LocaleUrlStrategy $strategy,
    ): void;

    public function addLocale(
        SiteId $siteId,
        string $locale,
        string $label,
        ?string $urlPrefix,
        string $hreflang,
        bool $enabled = true,
    ): void;

    public function updateLocale(
        SiteId $siteId,
        string $locale,
        string $label,
        ?string $urlPrefix,
        string $hreflang,
        bool $enabled,
    ): void;

    public function deleteLocale(SiteId $siteId, string $locale): bool;

    public function countPagesForLocale(SiteId $siteId, string $locale): int;

    /**
     * @param array<string, string> $localeToHost locale => host
     */
    public function replaceLocaleDomains(SiteId $siteId, array $localeToHost): void;

    /**
     * @return array<string, string> locale => host
     */
    public function localeDomains(SiteId $siteId): array;

    public function localeForHost(SiteId $siteId, string $host): ?string;
}
