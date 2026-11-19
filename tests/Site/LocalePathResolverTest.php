<?php

declare(strict_types=1);

namespace Nexis\Tests\Site;

use Nexis\Site\LocalePathResolver;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteLocale;
use Nexis\Site\TenantId;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class LocalePathResolverTest extends TestCase
{
    public function testPrefixSplitsLocaleAndPagePath(): void
    {
        $site = $this->site(LocaleUrlStrategy::Prefix, 'de', 'en');
        $resolver = new LocalePathResolver();

        $home = $resolver->resolve($site, '/de');
        $about = $resolver->resolve($site, '/en/about-us');
        $missing = $resolver->resolve($site, '/fr/bonjour');

        self::assertNotNull($home);
        self::assertSame('de', $home->locale);
        self::assertSame('/', $home->pagePath);
        self::assertNotNull($about);
        self::assertSame('en', $about->locale);
        self::assertSame('/about-us', $about->pagePath);
        self::assertNull($missing);
        self::assertSame('/nexis/en/about-us', $resolver->url($site, 'en', '/about-us', '/nexis'));
    }

    public function testNoneIgnoresPrefix(): void
    {
        $site = $this->site(LocaleUrlStrategy::None, 'de');
        $resolved = (new LocalePathResolver())->resolve($site, '/ueber-uns');

        self::assertNotNull($resolved);
        self::assertSame('de', $resolved->locale);
        self::assertSame('/ueber-uns', $resolved->pagePath);
    }

    public function testDomainResolvesLocaleFromHost(): void
    {
        $site = $this->site(LocaleUrlStrategy::Domain, 'de', 'en');
        $site = new Site(
            $site->id,
            $site->tenantId,
            $site->name,
            $site->primaryDomain,
            $site->defaultLocale,
            $site->localeUrlStrategy,
            $site->locales,
            [
                ['host' => 'de.example.test', 'locale' => 'de'],
                ['host' => 'en.example.test', 'locale' => 'en'],
            ],
        );
        $resolver = new LocalePathResolver();

        $de = $resolver->resolve($site, '/kontakt', 'de.example.test');
        $en = $resolver->resolve($site, '/contact', 'en.example.test');

        self::assertNotNull($de);
        self::assertSame('de', $de->locale);
        self::assertSame('/kontakt', $de->pagePath);
        self::assertNotNull($en);
        self::assertSame('en', $en->locale);
        self::assertSame('/contact', $en->pagePath);
        self::assertSame(
            'https://en.example.test/nexis/contact',
            $resolver->url($site, 'en', '/contact', '/nexis', 'https'),
        );
        self::assertSame(
            'http://de.example.test/nexis',
            $resolver->url($site, 'de', '/', '/nexis', 'http'),
        );
    }

    private function site(LocaleUrlStrategy $strategy, string ...$locales): Site
    {
        $items = [];
        foreach ($locales as $i => $locale) {
            $items[] = new SiteLocale(
                $locale,
                $locale,
                $strategy === LocaleUrlStrategy::None ? null : $locale,
                $locale,
                $i === 0,
                true,
            );
        }

        return new Site(
            new SiteId(Uuid::v7()),
            new TenantId(Uuid::v7()),
            'Test',
            'localhost',
            $locales[0],
            $strategy,
            $items,
        );
    }
}
