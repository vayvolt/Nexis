<?php

declare(strict_types=1);

namespace Nexis\Tests\Seo;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\Event\EventDispatcher;
use Nexis\Plugins\Catalog\CatalogProductJsonLd;
use Nexis\Seo\DocumentStructuredData;
use Nexis\Seo\StructuredDataBuilder;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteLocale;
use Nexis\Site\TenantId;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/plugins/nexis/catalog/src/CatalogProductJsonLd.php';

final class StructuredDataBuilderTest extends TestCase
{
    public function testExtractsFaqFromDocument(): void
    {
        $doc = [
            'schemaVersion' => 1,
            'root' => [
                'type' => 'core/section',
                'children' => [
                    [
                        'type' => 'nexis/faq/item',
                        'props' => ['question' => 'A?', 'answer' => 'B'],
                    ],
                ],
            ],
        ];
        $items = DocumentStructuredData::faqItems($doc);
        self::assertCount(1, $items);
        self::assertSame('A?', $items[0]['question']);
    }

    public function testBuildsWebpageAndFaqPage(): void
    {
        $builder = new StructuredDataBuilder(new EventDispatcher());
        $data = $builder->build(
            $this->page(PageType::PAGE, 'FAQ', 'index,follow'),
            $this->site(),
            'https://example.test/de/faq',
            [
                'schemaVersion' => 1,
                'root' => [
                    'type' => 'core/section',
                    'children' => [
                        [
                            'type' => 'nexis/faq/item',
                            'props' => ['question' => 'Was?', 'answer' => 'Das.'],
                        ],
                    ],
                ],
            ],
            '/assets/logo.svg',
        );
        self::assertNotNull($data);
        $types = array_map(static fn (array $n): mixed => $n['@type'] ?? null, $data['@graph']);
        self::assertContains(['WebPage', 'FAQPage'], $types);
        self::assertNotContains('Product', $types);
    }

    public function testCatalogPluginAddsProductOffer(): void
    {
        $events = new EventDispatcher();
        $events->listen(
            \Nexis\Event\StructuredDataBuilding::class,
            (new CatalogProductJsonLd())->onStructuredDataBuilding(...),
        );
        $builder = new StructuredDataBuilder($events);
        $data = $builder->build(
            $this->page(PageType::PRODUCT, 'Widget', 'index,follow', 'Ein Widget'),
            $this->site(),
            'https://example.test/de/catalog/widget',
            [
                'schemaVersion' => 1,
                'root' => [
                    'type' => 'core/section',
                    'children' => [
                        [
                            'type' => 'nexis/product/details',
                            'props' => [
                                'sku' => 'W-1',
                                'brand' => 'Nexis',
                                'price' => '19.90',
                                'currency' => 'EUR',
                                'availability' => 'InStock',
                            ],
                        ],
                    ],
                ],
            ],
        );
        self::assertNotNull($data);
        $product = null;
        foreach ($data['@graph'] as $node) {
            if (($node['@type'] ?? '') === 'Product') {
                $product = $node;
                break;
            }
        }
        self::assertNotNull($product);
        self::assertSame('W-1', $product['sku']);
        self::assertSame('19.90', $product['offers']['price']);
    }

    public function testCoreDoesNotEmitProductWithoutPlugin(): void
    {
        $builder = new StructuredDataBuilder(new EventDispatcher());
        $data = $builder->build(
            $this->page(PageType::PRODUCT, 'Widget', 'index,follow'),
            $this->site(),
            'https://example.test/de/catalog/widget',
        );
        self::assertNotNull($data);
        foreach ($data['@graph'] as $node) {
            self::assertNotSame('Product', $node['@type'] ?? null);
        }
    }

    public function testAbsolutizesRelativeCanonical(): void
    {
        $builder = new StructuredDataBuilder(new EventDispatcher());
        $data = $builder->build(
            $this->page(PageType::PAGE, 'Home', 'index,follow'),
            $this->site(),
            '/nexis/en/home',
            [],
            null,
            '/nexis',
            false,
            'http://localhost',
        );
        self::assertNotNull($data);
        self::assertSame('http://localhost/nexis/en/home', $data['@graph'][0]['url']);
    }

    public function testSkipsNoindex(): void
    {
        $builder = new StructuredDataBuilder(new EventDispatcher());
        $data = $builder->build(
            $this->page(PageType::PAGE, 'Private', 'noindex,nofollow'),
            $this->site(),
            'https://example.test/de/private',
        );
        self::assertNull($data);
    }

    private function page(string $type, string $title, string $robots, string $metaDescription = ''): Page
    {
        return new Page(
            new PageId(Uuid::v7()),
            new SiteId(Uuid::v7()),
            Uuid::v7(),
            'de',
            'slug',
            '/slug',
            $title,
            null,
            PageStatus::Published,
            type: $type,
            metaDescription: $metaDescription !== '' ? $metaDescription : null,
            robots: $robots,
        );
    }

    private function site(): Site
    {
        $locale = new SiteLocale('de', 'Deutsch', 'de', 'de', true, true);

        return new Site(
            new SiteId(Uuid::v7()),
            new TenantId(Uuid::v7()),
            'Demo',
            'demo.test',
            'de',
            LocaleUrlStrategy::Prefix,
            [$locale],
        );
    }
}
