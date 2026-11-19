<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

use Nexis\Event\StructuredDataBuilding;
use Nexis\Seo\DocumentStructuredData;

/**
 * Adds Product (+ optional Offer) JSON-LD for pages with type=product.
 */
final class CatalogProductJsonLd
{
    private const SCHEMA = 'https://schema.org';

    public function onStructuredDataBuilding(StructuredDataBuilding $event): void
    {
        if (!$event->page->isProduct) {
            return;
        }

        $details = $this->productDetails($event->document);
        $image = $details['image']
            ?? DocumentStructuredData::firstImageSrc($event->document, $event->basePath)
            ?? $event->logoUrl;

        $product = [
            '@type' => 'Product',
            '@id' => $event->canonicalUrl . '#product',
            'name' => $details['name'] ?? $event->page->title,
            'url' => $event->canonicalUrl,
            'mainEntityOfPage' => ['@id' => $event->webpageId],
        ];
        $desc = $event->page->documentDescription;
        if ($desc !== '') {
            $product['description'] = $desc;
        }
        $absImage = $this->absoluteUrl($image, $event->canonicalUrl);
        if ($absImage !== null) {
            $product['image'] = [$absImage];
        }
        if (($details['sku'] ?? null) !== null) {
            $product['sku'] = $details['sku'];
        }
        if (($details['brand'] ?? null) !== null) {
            $product['brand'] = [
                '@type' => 'Brand',
                'name' => $details['brand'],
            ];
        }
        $offer = $this->offer($details, $event->canonicalUrl);
        if ($offer !== null) {
            $product['offers'] = $offer;
        }

        $event->append($product);
    }

    /**
     * @param array<string, mixed> $document
     * @return array{name: ?string, sku: ?string, brand: ?string, price: ?string, currency: ?string, availability: ?string, image: ?string}
     */
    private function productDetails(array $document): array
    {
        $found = null;
        DocumentStructuredData::walkDocument($document, static function (array $block) use (&$found): void {
            if ($found !== null || ($block['type'] ?? '') !== 'nexis/product/details') {
                return;
            }
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $found = [
                'name' => self::nullableTrim((string) ($props['name'] ?? '')),
                'sku' => self::nullableTrim((string) ($props['sku'] ?? '')),
                'brand' => self::nullableTrim((string) ($props['brand'] ?? '')),
                'price' => self::nullableTrim((string) ($props['price'] ?? '')),
                'currency' => self::nullableTrim((string) ($props['currency'] ?? '')),
                'availability' => self::nullableTrim((string) ($props['availability'] ?? '')),
                'image' => self::nullableTrim((string) ($props['image'] ?? '')),
            ];
        });

        return $found ?? [
            'name' => null,
            'sku' => null,
            'brand' => null,
            'price' => null,
            'currency' => null,
            'availability' => null,
            'image' => null,
        ];
    }

    /**
     * @param array{name: ?string, sku: ?string, brand: ?string, price: ?string, currency: ?string, availability: ?string, image: ?string} $details
     * @return array<string, mixed>|null
     */
    private function offer(array $details, string $canonicalUrl): ?array
    {
        $price = $details['price'] ?? null;
        if ($price === null || !is_numeric(str_replace(',', '.', $price))) {
            return null;
        }
        $normalized = str_replace(',', '.', $price);
        $currency = strtoupper((string) ($details['currency'] ?? 'EUR'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $currency = 'EUR';
        }

        return [
            '@type' => 'Offer',
            'url' => $canonicalUrl,
            'price' => $normalized,
            'priceCurrency' => $currency,
            'availability' => self::SCHEMA . '/' . self::availabilityToken($details['availability'] ?? 'InStock'),
        ];
    }

    private static function availabilityToken(?string $value): string
    {
        $key = strtolower(trim((string) $value));
        $map = [
            'instock' => 'InStock',
            'in_stock' => 'InStock',
            'available' => 'InStock',
            'outofstock' => 'OutOfStock',
            'out_of_stock' => 'OutOfStock',
            'soldout' => 'OutOfStock',
            'preorder' => 'PreOrder',
            'pre_order' => 'PreOrder',
            'discontinued' => 'Discontinued',
            'limitedavailability' => 'LimitedAvailability',
        ];
        if (in_array($value, ['InStock', 'OutOfStock', 'PreOrder', 'Discontinued', 'LimitedAvailability'], true)) {
            return $value;
        }

        return $map[$key] ?? 'InStock';
    }

    private function absoluteUrl(?string $url, string $canonicalUrl): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        $parts = parse_url($canonicalUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }

        return $origin . '/' . ltrim($url, '/');
    }

    private static function nullableTrim(string $value): ?string
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}
