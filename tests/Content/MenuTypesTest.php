<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\MenuId;
use Nexis\Content\MenuItem;
use Nexis\Content\NavLink;
use Nexis\Content\PageId;
use PHPUnit\Framework\TestCase;

final class MenuTypesTest extends TestCase
{
    public function testNavLinkHoldsLabelAndUrl(): void
    {
        $link = new NavLink('Start', '/nexis/de');
        self::assertSame('Start', $link->label);
        self::assertSame('/nexis/de', $link->url);
    }

    public function testMenuItemAcceptsPageReference(): void
    {
        $pageId = new PageId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0001');
        $item = new MenuItem('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0002', 'Über uns', $pageId, null, 1);
        self::assertSame('Über uns', $item->label);
        self::assertNotNull($item->pageId);
        self::assertSame($pageId->value, $item->pageId->value);
        self::assertNull($item->url);
    }

    public function testMenuIdValidatesUuid(): void
    {
        $id = new MenuId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0003');
        self::assertSame('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0003', $id->value);
    }

    public function testMenuItemHoldsChildren(): void
    {
        $child = new MenuItem('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0004', 'Team', null, '/team', 0);
        $parent = new MenuItem('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0005', 'Über uns', null, null, 0, null, [$child]);
        self::assertCount(1, $parent->children);
        self::assertSame('Team', $parent->children[0]->label);
    }

    public function testNavLinkToArrayIncludesChildren(): void
    {
        $link = new NavLink('Über uns', '/de/ueber-uns', [new NavLink('Team', '/de/team')]);
        $array = $link->toArray();
        self::assertSame('Über uns', $array['label']);
        self::assertCount(1, $array['children']);
        self::assertSame('Team', $array['children'][0]['label']);
    }
}
