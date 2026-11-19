<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

use Nexis\Builder\Core\AbstractCoreBlock;
use Nexis\Content\PageRepository;
use Nexis\Content\PageType;
use Nexis\I18n\PublicUi;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;

final class CatalogProductsBlock extends AbstractCoreBlock
{
    public function __construct(
        private PageRepository $pages,
        private SiteRepository $sites,
        private LocalePathResolver $paths,
        private PublicUi $ui,
    ) {
    }

    public function type(): string
    {
        return 'nexis/catalog/products';
    }

    public function label(): string
    {
        return 'Katalog-Produkte';
    }

    public function defaultProps(): array
    {
        return [
            'heading' => 'Produkte',
            'limit' => 6,
            'emptyText' => 'Noch keine Produkte.',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'heading' => (object) ['type' => 'string'],
                'limit' => (object) ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                'emptyText' => (object) ['type' => 'string'],
            ],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $locale = (string) ($context['locale'] ?? 'de');
        $heading = (string) ($props['heading'] ?? '');
        if ($heading === '' || $heading === 'Produkte') {
            $heading = $this->ui->get($locale, 'public.catalog.heading_default');
        }
        $limit = (int) ($props['limit'] ?? 6);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        $empty = (string) ($props['emptyText'] ?? '');
        if ($empty === '' || $empty === 'Noch keine Produkte.') {
            $empty = $this->ui->get($locale, 'public.catalog.empty_block');
        }
        $basePath = (string) ($context['basePath'] ?? '');
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $site = $context['site'] ?? null;
        if (!$site instanceof Site) {
            $site = $this->sites->installed();
        }
        if (!$site instanceof Site) {
            return '';
        }

        $products = $this->pages->listPublishedByTypeForLocale($site->id, $locale, PageType::PRODUCT);
        $products = array_slice($products, 0, $limit);

        $html = '<section class="nx-catalog-products">';
        if ($heading !== '') {
            $html .= '<h2>' . $e($heading) . '</h2>';
        }
        if ($products === []) {
            $html .= '<p class="nx-catalog-products__empty">' . $e($empty) . '</p>';
        } else {
            $html .= '<ul class="nx-catalog-products__list">';
            foreach ($products as $product) {
                $url = $this->paths->url($site, $product->locale, $product->path, $basePath);
                $html .= '<li><a href="' . $e($url) . '">' . $e($product->title) . '</a>';
                $excerpt = $product->documentDescription;
                if ($excerpt !== '') {
                    $html .= '<p>' . $e($excerpt) . '</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $archive = $this->paths->url($site, $locale, CatalogPaths::archivePath(), $basePath);
        $html .= '<p class="nx-catalog-products__more"><a href="' . $e($archive) . '">'
            . $e($this->ui->get($locale, 'public.catalog.more')) . '</a></p>';
        $html .= '</section>';

        return $html;
    }
}
