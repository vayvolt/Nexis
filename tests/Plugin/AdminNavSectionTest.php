<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\Site\SiteId;
use PHPUnit\Framework\TestCase;

final class AdminNavSectionTest extends TestCase
{
    use PluginKernelTestFactory;

    public function testMatchesPathAndPageType(): void
    {
        $kernel = $this->makePluginKernel();
        $kernel->forPlugin('nexis/blog');
        $kernel->registerAdminNavSection('blog', [
            'paths' => ['/admin/blog'],
            'pageTypes' => [PageType::POST],
        ]);

        self::assertSame('blog', $kernel->matchAdminNav('/admin/blog', '', null));
        self::assertSame('blog', $kernel->matchAdminNav('/nexis/admin/blog/new', '/nexis', null));
        self::assertSame('blog', $kernel->matchAdminNav('/admin/pages/abc', '', $this->page(PageType::POST)));
        self::assertNull($kernel->matchAdminNav('/admin/pages/abc', '', $this->page(PageType::PAGE)));
        self::assertNull($kernel->matchAdminNav('/admin/menus', '', null));
    }

    private function page(string $type): Page
    {
        return new Page(
            new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0600'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0601'),
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0602',
            'de',
            'demo',
            '/demo',
            'Demo',
            null,
            PageStatus::Draft,
            null,
            null,
            null,
            null,
            null,
            'index,follow',
            null,
            null,
            $type,
        );
    }
}
