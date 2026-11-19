<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\Menu;
use Nexis\Content\MenuId;
use Nexis\Content\MenuItem;
use Nexis\Content\MenuRepository;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageNavigationSync;
use Nexis\Content\PageStatus;
use Nexis\Content\PageType;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteLocale;
use Nexis\Site\TenantId;
use PHPUnit\Framework\TestCase;

final class PageNavigationSyncTest extends TestCase
{
    public function testAddsPageToPrimaryMenu(): void
    {
        $siteId = new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0100');
        $pageId = new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0101');
        $menuId = new MenuId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0102');
        $site = $this->site($siteId);
        $page = $this->page($siteId, $pageId, 'de', 'Kontakt');
        $menu = new Menu($menuId, $siteId, 'primary', 'Hauptnavigation', 'de');

        $menus = $this->createMock(MenuRepository::class);
        $menus->expects(self::once())
            ->method('ensure')
            ->with($siteId, 'primary', 'Hauptnavigation', 'de')
            ->willReturn($menu);
        $menus->expects(self::once())
            ->method('itemsFor')
            ->with($menuId)
            ->willReturn([]);
        $menus->expects(self::once())
            ->method('appendRootItem')
            ->with($menuId, 'Kontakt', $pageId->value, null);

        (new PageNavigationSync($menus, $this->ui()))->addToPrimary($site, $page);
    }

    public function testSkipsWhenPageAlreadyLinked(): void
    {
        $siteId = new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0200');
        $pageId = new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0201');
        $menuId = new MenuId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0202');
        $site = $this->site($siteId);
        $page = $this->page($siteId, $pageId, 'de', 'Kontakt');
        $menu = new Menu($menuId, $siteId, 'primary', 'Hauptnavigation', 'de');

        $menus = $this->createMock(MenuRepository::class);
        $menus->method('ensure')->willReturn($menu);
        $menus->method('itemsFor')->willReturn([
            new MenuItem('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0203', 'Kontakt', $pageId, null, 0),
        ]);
        $menus->expects(self::never())->method('appendRootItem');

        (new PageNavigationSync($menus, $this->ui()))->addToPrimary($site, $page);
    }

    public function testSkipsNonPageTypes(): void
    {
        $siteId = new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0500');
        $pageId = new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0501');
        $site = $this->site($siteId);
        $page = $this->page($siteId, $pageId, 'de', 'Blog Post', PageType::POST);

        $menus = $this->createMock(MenuRepository::class);
        $menus->expects(self::never())->method('ensure');
        $menus->expects(self::never())->method('appendRootItem');

        (new PageNavigationSync($menus, $this->ui()))->addToPrimary($site, $page);
    }

    private function ui(): PublicUi
    {
        return new PublicUi(new Translator(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang',
        ));
    }

    private function site(SiteId $siteId): Site
    {
        return new Site(
            $siteId,
            new TenantId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0300'),
            'Demo',
            'localhost',
            'de',
            LocaleUrlStrategy::Prefix,
            [new SiteLocale('de', 'Deutsch', 'de', 'de', true, true)],
        );
    }

    private function page(SiteId $siteId, PageId $pageId, string $locale, string $title, string $type = PageType::PAGE): Page
    {
        return new Page(
            $pageId,
            $siteId,
            '0193f0a0-7c2a-7e11-9c00-5f3c1a9b0400',
            $locale,
            'kontakt',
            '/kontakt',
            $title,
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
