<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugins\Catalog\ProductDetailsBlock;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/plugins/nexis/catalog/src/ProductDetailsBlock.php';

final class CatalogProductDetailsBlockTest extends TestCase
{
    public function testRendersPriceAndSku(): void
    {
        $html = (new ProductDetailsBlock())->render([
            'type' => 'nexis/product/details',
            'props' => [
                'sku' => 'SKU-9',
                'price' => '12.50',
                'currency' => 'EUR',
                'availability' => 'InStock',
            ],
        ], []);
        self::assertStringContainsString('nx-product-details', $html);
        self::assertStringContainsString('SKU-9', $html);
        self::assertStringContainsString('12.50 EUR', $html);
    }
}
