<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

use Nexis\Builder\Core\AbstractCoreBlock;

/**
 * Optional product fields for catalog pages (Price/SKU → JSON-LD Offer).
 * Lives in the catalog plugin; shop/checkout stays out of core.
 */
final class ProductDetailsBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'nexis/product/details';
    }

    public function label(): string
    {
        return 'Produktdaten';
    }

    public function defaultProps(): array
    {
        return [
            'name' => '',
            'sku' => '',
            'brand' => '',
            'price' => '',
            'currency' => 'EUR',
            'availability' => 'InStock',
            'image' => '',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'name' => (object) ['type' => 'string'],
                'sku' => (object) ['type' => 'string'],
                'brand' => (object) ['type' => 'string'],
                'price' => (object) ['type' => 'string'],
                'currency' => (object) ['type' => 'string'],
                'availability' => (object) [
                    'type' => 'string',
                    'enum' => ['InStock', 'OutOfStock', 'PreOrder', 'Discontinued', 'LimitedAvailability'],
                ],
                'image' => (object) ['type' => 'string'],
            ],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $rows = [];

        $name = trim((string) ($props['name'] ?? ''));
        if ($name !== '') {
            $rows[] = $this->row('Name', $name);
        }
        $brand = trim((string) ($props['brand'] ?? ''));
        if ($brand !== '') {
            $rows[] = $this->row('Marke', $brand);
        }
        $sku = trim((string) ($props['sku'] ?? ''));
        if ($sku !== '') {
            $rows[] = $this->row('SKU', $sku);
        }
        $price = trim((string) ($props['price'] ?? ''));
        $currency = trim((string) ($props['currency'] ?? ''));
        if ($price !== '') {
            $rows[] = $this->row('Preis', $currency !== '' ? $price . ' ' . $currency : $price);
        }
        $availability = trim((string) ($props['availability'] ?? ''));
        if ($availability !== '') {
            $rows[] = $this->row('Verfügbarkeit', self::availabilityLabel($availability));
        }

        if ($rows === []) {
            return '';
        }

        return '<aside class="nx-product-details"><dl class="nx-product-details__list">'
            . implode('', $rows) . '</dl></aside>';
    }

    private function row(string $label, string $value): string
    {
        return '<div class="nx-product-details__row"><dt>' . $this->e($label)
            . '</dt><dd>' . $this->e($value) . '</dd></div>';
    }

    private static function availabilityLabel(string $value): string
    {
        return match ($value) {
            'InStock' => 'Auf Lager',
            'OutOfStock' => 'Nicht vorrätig',
            'PreOrder' => 'Vorbestellung',
            'Discontinued' => 'Eingestellt',
            'LimitedAvailability' => 'Begrenzt verfügbar',
            default => $value,
        };
    }
}
