<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\I18n\LocalizedMap;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteId;

/**
 * Site-wide chrome snippets (header CTA, footer teaser) stored in site_settings.
 */
final class GlobalContent
{
    public const KEY_HEADER_CTA_LABEL = 'content.global.header_cta_label';
    public const KEY_HEADER_CTA_HREF = 'content.global.header_cta_href';
    public const KEY_FOOTER_TEASER = 'content.global.footer_teaser';

    public function __construct(
        private PdoSiteSettingsRepository $settings,
    ) {
    }

    /**
     * @return array{
     *   headerCtaLabel: array<string, string>,
     *   headerCtaHref: array<string, string>,
     *   footerTeaser: array<string, string>
     * }
     */
    public function loadMaps(SiteId $siteId): array
    {
        return [
            'headerCtaLabel' => LocalizedMap::decode($this->settings->get($siteId, self::KEY_HEADER_CTA_LABEL, '{}')),
            'headerCtaHref' => LocalizedMap::decode($this->settings->get($siteId, self::KEY_HEADER_CTA_HREF, '{}')),
            'footerTeaser' => LocalizedMap::decode($this->settings->get($siteId, self::KEY_FOOTER_TEASER, '{}')),
        ];
    }

    /**
     * @param array<string, string> $headerCtaLabel
     * @param array<string, string> $headerCtaHref
     * @param array<string, string> $footerTeaser
     */
    public function saveMaps(
        SiteId $siteId,
        array $headerCtaLabel,
        array $headerCtaHref,
        array $footerTeaser,
    ): void {
        $this->settings->set($siteId, self::KEY_HEADER_CTA_LABEL, $headerCtaLabel);
        $this->settings->set($siteId, self::KEY_HEADER_CTA_HREF, $headerCtaHref);
        $this->settings->set($siteId, self::KEY_FOOTER_TEASER, $footerTeaser);
    }

    /**
     * @return array{label: string, href: string, teaser: string}
     */
    public function resolve(Site $site, string $locale): array
    {
        $maps = $this->loadMaps($site->id);
        $fallback = $site->defaultLocale;

        return [
            'label' => LocalizedMap::get($maps['headerCtaLabel'], $locale, $fallback),
            'href' => LocalizedMap::get($maps['headerCtaHref'], $locale, $fallback),
            'teaser' => LocalizedMap::get($maps['footerTeaser'], $locale, $fallback),
        ];
    }

    public function renderHeaderCta(Site $site, string $locale, string $basePath): string
    {
        $resolved = $this->resolve($site, $locale);
        $label = $resolved['label'];
        $href = $this->normalizeHref($resolved['href'], $basePath);
        if ($label === '' || $href === '') {
            return '';
        }

        return '<a class="bk-global-cta" href="' . $this->e($href) . '">' . $this->e($label) . '</a>';
    }

    public function renderFooterTeaser(Site $site, string $locale): string
    {
        $teaser = $this->resolve($site, $locale)['teaser'];
        if ($teaser === '') {
            return '';
        }

        return '<div class="bk-global-teaser"><p>' . $this->e($teaser) . '</p></div>';
    }

    private function normalizeHref(string $href, string $basePath): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $href) === 1 || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return $href;
        }
        if (str_starts_with($href, '#')) {
            return $href;
        }
        $base = rtrim($basePath, '/');
        if (str_starts_with($href, '/')) {
            return $base . $href;
        }

        return $base . '/' . ltrim($href, '/');
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
