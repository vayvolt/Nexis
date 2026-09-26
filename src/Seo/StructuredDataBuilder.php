<?php

declare(strict_types=1);

namespace Nexis\Seo;

use Nexis\Content\Page;
use Nexis\Event\EventDispatcher;
use Nexis\Event\StructuredDataBuilding;
use Nexis\Site\Site;

/**
 * Builds core schema.org JSON-LD (@graph): WebPage, FAQPage, WebSite, Organization.
 * Product/Offer and other commerce types come from plugins via StructuredDataBuilding.
 */
final class StructuredDataBuilder
{
    private const SCHEMA = 'https://schema.org';

    public function __construct(
        private EventDispatcher $events,
    ) {
    }

    /**
     * @param array<string, mixed> $document Builder snapshot / revision document
     * @return array<string, mixed>|null Graph payload, or null when page should not expose structured data
     */
    public function build(
        Page $page,
        Site $site,
        string $canonicalUrl,
        array $document = [],
        ?string $logoUrl = null,
        string $basePath = '',
        bool $isPreview = false,
        ?string $siteOrigin = null,
    ): ?array {
        if ($isPreview || $this->isNoindex($page->robots)) {
            return null;
        }
        if ($canonicalUrl === '') {
            return null;
        }

        $canonicalUrl = $this->absolutize($canonicalUrl, $siteOrigin);
        if ($canonicalUrl === '' || !preg_match('#^https?://#i', $canonicalUrl)) {
            return null;
        }

        $faqItems = DocumentStructuredData::faqItems($document);
        $image = DocumentStructuredData::firstImageSrc($document, $basePath)
            ?? $this->absoluteUrl($logoUrl, $canonicalUrl);

        $graph = [];
        $webpageId = $canonicalUrl . '#webpage';
        $websiteId = $this->origin($canonicalUrl) . '/#website';
        $orgId = $this->origin($canonicalUrl) . '/#organization';

        $webpage = [
            '@type' => 'WebPage',
            '@id' => $webpageId,
            'url' => $canonicalUrl,
            'name' => $page->documentTitle,
            'isPartOf' => ['@id' => $websiteId],
            'inLanguage' => $page->locale,
        ];
        $desc = $page->documentDescription;
        if ($desc !== '') {
            $webpage['description'] = $desc;
        }
        if ($image !== null && $image !== '') {
            $webpage['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $this->absoluteUrl($image, $canonicalUrl),
            ];
        }
        if ($faqItems !== []) {
            $webpage['@type'] = ['WebPage', 'FAQPage'];
            $webpage['mainEntity'] = array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'] !== '' ? $item['answer'] : $item['question'],
                    ],
                ],
                $faqItems,
            );
        }
        $graph[] = $webpage;

        $graph[] = [
            '@type' => 'WebSite',
            '@id' => $websiteId,
            'url' => $this->origin($canonicalUrl) . '/',
            'name' => $site->name,
            'publisher' => ['@id' => $orgId],
            'inLanguage' => $page->locale,
        ];

        $org = [
            '@type' => 'Organization',
            '@id' => $orgId,
            'name' => $site->name,
            'url' => $this->origin($canonicalUrl) . '/',
        ];
        if ($logoUrl !== null && $logoUrl !== '') {
            $org['logo'] = $this->absoluteUrl($logoUrl, $canonicalUrl);
        }
        $graph[] = $org;

        $event = new StructuredDataBuilding(
            $page,
            $site,
            $canonicalUrl,
            $document,
            $webpageId,
            $basePath,
            $logoUrl,
            $graph,
        );
        $this->events->dispatch($event);

        return [
            '@context' => self::SCHEMA,
            '@graph' => $event->graph,
        ];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function toScriptTag(?array $data): string
    {
        if ($data === null || $data === []) {
            return '';
        }
        $json = json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS,
        );
        if (!is_string($json) || $json === '') {
            return '';
        }

        return '<script type="application/ld+json">' . $json . '</script>' . "\n";
    }

    private function isNoindex(string $robots): bool
    {
        return str_contains(strtolower($robots), 'noindex');
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    private function absolutize(string $url, ?string $siteOrigin): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }
        $origin = is_string($siteOrigin) ? rtrim($siteOrigin, '/') : '';
        if ($origin === '' || preg_match('#^https?://#i', $origin) !== 1) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            $scheme = parse_url($origin, PHP_URL_SCHEME) ?: 'https';

            return $scheme . ':' . $url;
        }
        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }

        return $origin . '/' . ltrim($url, '/');
    }

    private function absoluteUrl(?string $url, string $canonicalUrl): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $absolute = $this->absolutize($url, $this->origin($canonicalUrl));

        return preg_match('#^https?://#i', $absolute) === 1 ? $absolute : null;
    }
}
