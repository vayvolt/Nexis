<?php

declare(strict_types=1);

namespace Nexis\Site;

final class Site
{
    /**
     * @param list<SiteLocale> $locales
     * @param list<array{host: string, locale: ?string}> $domains
     */
    public function __construct(
        public private(set) SiteId $id,
        public private(set) TenantId $tenantId,
        public private(set) string $name,
        public private(set) string $primaryDomain,
        public private(set) string $defaultLocale,
        public private(set) LocaleUrlStrategy $localeUrlStrategy,
        public private(set) array $locales,
        public private(set) array $domains = [],
    ) {
    }

    public function locale(string $code): ?SiteLocale
    {
        return array_find(
            $this->locales,
            static fn (SiteLocale $locale): bool => $locale->locale === $code && $locale->enabled,
        );
    }

    /**
     * @return list<SiteLocale>
     */
    public function enabledLocales(): array
    {
        return array_values(array_filter(
            $this->locales,
            static fn (SiteLocale $locale): bool => $locale->enabled,
        ));
    }

    public function defaultSiteLocale(): SiteLocale
    {
        $default = $this->defaultLocale;
        $match = array_find($this->locales, static fn (SiteLocale $locale): bool => $locale->isDefault && $locale->enabled)
            ?? array_find($this->locales, static fn (SiteLocale $locale): bool => $locale->locale === $default && $locale->enabled)
            ?? array_find($this->locales, static fn (SiteLocale $locale): bool => $locale->enabled);

        if ($match instanceof SiteLocale) {
            return $match;
        }

        return new SiteLocale($this->defaultLocale, $this->defaultLocale, null, $this->defaultLocale, true, true);
    }
}
