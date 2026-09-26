<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\BlockRegistry;
use Nexis\Builder\Core\HeadingBlock;
use Nexis\Builder\Core\SectionBlock;
use PHPUnit\Framework\TestCase;

final class BlockRegistryCatalogTest extends TestCase
{
    public function testCatalogIncludesSchemaAndDefaults(): void
    {
        $registry = new BlockRegistry([new SectionBlock(), new HeadingBlock()]);
        $catalog = $registry->catalog();
        self::assertCount(2, $catalog);
        self::assertSame('core/section', $catalog[0]['type']);
        self::assertTrue($catalog[0]['allowsChildren']);
        self::assertSame(['width' => 'wide', 'padding' => 'lg'], $catalog[0]['defaultProps']);
        self::assertFalse($catalog[1]['allowsChildren']);
    }
}
