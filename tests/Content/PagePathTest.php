<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\PagePath;
use PHPUnit\Framework\TestCase;

final class PagePathTest extends TestCase
{
    public function testUnderBuildsNestedPath(): void
    {
        self::assertSame('/blog/hallo-welt', PagePath::under('blog', 'Hallo Welt'));
        self::assertSame('/blog', PagePath::under('blog', ''));
    }

    public function testReplaceLeafKeepsParent(): void
    {
        self::assertSame('/blog/neu', PagePath::replaceLeaf('/blog/alt', 'neu'));
        self::assertSame('/ueber-uns', PagePath::replaceLeaf('/ueber-uns', 'ueber-uns'));
    }
}
