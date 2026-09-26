<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageRepository;
use Nexis\Content\PageStatus;
use Nexis\Http\Controller\RobotsTxtController;
use Nexis\Http\Controller\SitemapController;
use Nexis\Http\ResponseFactory;
use Nexis\Http\SitemapArchiveProvider;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteLocale;
use Nexis\Site\SiteRepository;
use Nexis\Site\TenantId;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class SeoEndpointsTest extends TestCase
{
    public function testResponseFactoryXmlAndText(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);

        $xml = $responses->xml('<root/>');
        self::assertSame(200, $xml->getStatusCode());
        self::assertStringContainsString('application/xml', $xml->getHeaderLine('Content-Type'));
        self::assertSame('<root/>', (string) $xml->getBody());

        $text = $responses->text("User-agent: *\n");
        self::assertStringContainsString('text/plain', $text->getHeaderLine('Content-Type'));
        self::assertStringContainsString('User-agent', (string) $text->getBody());
    }

    public function testRobotsTxtMentionsSitemapAndDisallowsAdmin(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $sites = $this->createMock(SiteRepository::class);
        $sites->method('installed')->willReturn(null);

        $controller = new RobotsTxtController($responses, $sites);
        $request = (new ServerRequest('GET', 'http://localhost/nexis/robots.txt'))
            ->withAttribute('base_path', '/nexis');

        $response = $controller($request);
        $body = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Disallow: /nexis/admin', $body);
        self::assertStringContainsString('Disallow: /nexis/preview', $body);
        self::assertStringContainsString('Disallow: /nexis/account', $body);
        self::assertStringContainsString('Disallow: /nexis/install', $body);
        self::assertStringNotContainsString('Sitemap:', $body);
    }

    public function testRobotsTxtIncludesSitemapWhenSiteExists(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $site = $this->demoSite();
        $sites = $this->createMock(SiteRepository::class);
        $sites->method('installed')->willReturn($site);

        $controller = new RobotsTxtController($responses, $sites);
        $request = (new ServerRequest('GET', 'http://localhost/nexis/robots.txt'))
            ->withAttribute('base_path', '/nexis');

        $body = (string) $controller($request)->getBody();
        self::assertStringContainsString('Sitemap: http://localhost/nexis/sitemap.xml', $body);
    }

    public function testSitemapIndexListsLocaleSitemaps(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $site = $this->demoSite();
        $sites = $this->createMock(SiteRepository::class);
        $sites->method('installed')->willReturn($site);

        $de = $this->page('de', '/', 'Start');
        $en = $this->page('en', '/', 'Home');

        $pages = $this->createMock(PageRepository::class);
        $pages->method('listPublishedForLocale')->willReturnCallback(
            static function (SiteId $siteId, string $locale) use ($de, $en): array {
                return match ($locale) {
                    'de' => [$de],
                    'en' => [$en],
                    default => [],
                };
            },
        );

        $controller = new SitemapController($responses, $sites, $pages, new LocalePathResolver(), $this->emptyArchives(), $this->publicUi());
        $request = (new ServerRequest('GET', 'http://localhost/nexis/sitemap.xml'))
            ->withAttribute('base_path', '/nexis');
        $body = (string) $controller->index($request)->getBody();

        self::assertStringContainsString('<sitemapindex', $body);
        self::assertStringContainsString('/sitemap-de.xml', $body);
        self::assertStringContainsString('/sitemap-en.xml', $body);
    }

    public function testLocaleSitemapIncludesHreflangAlternates(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $site = $this->demoSite();
        $sites = $this->createMock(SiteRepository::class);
        $sites->method('installed')->willReturn($site);

        $de = $this->page('de', '/', 'Start', new \DateTimeImmutable('2026-09-01 12:00:00'));
        $en = $this->page('en', '/', 'Home');

        $pages = $this->createMock(PageRepository::class);
        $pages->method('listPublishedForLocale')->willReturn([$de]);
        $pages->method('alternates')->willReturn([$de, $en]);

        $controller = new SitemapController($responses, $sites, $pages, new LocalePathResolver(), $this->emptyArchives(), $this->publicUi());
        $request = (new ServerRequest('GET', 'http://localhost/nexis/sitemap-de.xml'))
            ->withAttribute('base_path', '/nexis')
            ->withAttribute('locale', 'de');
        $body = (string) $controller->locale($request)->getBody();

        self::assertStringContainsString('xmlns:xhtml=', $body);
        self::assertStringContainsString('hreflang="de"', $body);
        self::assertStringContainsString('hreflang="en"', $body);
        self::assertStringContainsString('hreflang="x-default"', $body);
        self::assertStringContainsString('/de"', $body);
        self::assertStringContainsString('/en"', $body);
        self::assertStringContainsString('<lastmod>2026-09-01</lastmod>', $body);
    }

    public function testLocaleSitemapIncludesEnabledArchives(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $site = $this->demoSite();
        $sites = $this->createMock(SiteRepository::class);
        $sites->method('installed')->willReturn($site);

        $pages = $this->createMock(PageRepository::class);
        $pages->method('listPublishedForLocale')->willReturn([$this->page('de', '/', 'Start')]);
        $pages->method('alternates')->willReturn([]);

        $archives = $this->createMock(SitemapArchiveProvider::class);
        $archives->method('pathsFor')->willReturn(['/blog', '/catalog']);

        $controller = new SitemapController($responses, $sites, $pages, new LocalePathResolver(), $archives, $this->publicUi());
        $request = (new ServerRequest('GET', 'http://localhost/nexis/sitemap-de.xml'))
            ->withAttribute('base_path', '/nexis')
            ->withAttribute('locale', 'de');
        $body = (string) $controller->locale($request)->getBody();

        self::assertStringContainsString('/de/blog', $body);
        self::assertStringContainsString('/de/catalog', $body);
    }

    private function emptyArchives(): SitemapArchiveProvider
    {
        $archives = $this->createMock(SitemapArchiveProvider::class);
        $archives->method('pathsFor')->willReturn([]);

        return $archives;
    }

    private function publicUi(): PublicUi
    {
        return new PublicUi(new Translator(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang',
        ));
    }

    private function demoSite(): Site
    {
        return new Site(
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b00aa'),
            new TenantId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b00ab'),
            'Demo',
            'localhost',
            'de',
            LocaleUrlStrategy::Prefix,
            [
                new SiteLocale('de', 'Deutsch', 'de', 'de', true, true),
                new SiteLocale('en', 'English', 'en', 'en', false, true),
            ],
        );
    }

    private function page(string $locale, string $path, string $title, ?\DateTimeImmutable $updatedAt = null): Page
    {
        return new Page(
            new PageId($locale === 'de'
                ? '0193f0a0-7c2a-7e11-9c00-5f3c1a9b00d1'
                : '0193f0a0-7c2a-7e11-9c00-5f3c1a9b00e1'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b00aa'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b00ac',
            $locale,
            'home',
            $path,
            $title,
            null,
            PageStatus::Published,
            publishedSnapshotId: new \Nexis\Builder\SnapshotId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b00ad'),
            updatedAt: $updatedAt,
        );
    }
}
