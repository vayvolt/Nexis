<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\Core\FaqAccordionBlock;
use Nexis\Builder\Core\FaqItemBlock;
use PHPUnit\Framework\TestCase;

final class FaqBlocksTest extends TestCase
{
    public function testItemRendersDetails(): void
    {
        $html = (new FaqItemBlock())->render([
            'type' => 'nexis/faq/item',
            'props' => ['question' => 'Was kostet es?', 'answer' => "Zeile 1\nZeile 2"],
        ], []);
        self::assertStringContainsString('<details', $html);
        self::assertStringContainsString('Was kostet es?', $html);
        self::assertStringContainsString('Zeile 1', $html);
    }

    public function testAccordionWrapsChildren(): void
    {
        $html = (new FaqAccordionBlock())->render([
            'type' => 'nexis/faq/accordion',
            'props' => ['heading' => 'FAQ'],
            'children' => [],
        ], []);
        self::assertStringContainsString('nx-faq', $html);
        self::assertStringContainsString('FAQ', $html);
    }
}
