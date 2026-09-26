<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

use Nexis\Event\StructuredDataBuilding;
use Nexis\Http\Route;
use Nexis\Plugin\PluginAssetRegistry;
use Nexis\Plugin\PluginKernel;
use Nexis\Plugin\PluginServiceProvider;
use Psr\Container\ContainerInterface;

final class CatalogServiceProvider implements PluginServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(PluginKernel $kernel, ContainerInterface $container): void
    {
        $kernel->registerBlock($container->get(CatalogProductsBlock::class));
        $kernel->registerBlock($container->get(ProductDetailsBlock::class));
        $kernel->listen(
            StructuredDataBuilding::class,
            $container->get(CatalogProductJsonLd::class)->onStructuredDataBuilding(...),
        );
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/catalog',
            static fn ($request) => $container->get(CatalogAdminController::class)->index($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/catalog/new',
            static fn ($request) => $container->get(CatalogAdminController::class)->createForm($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/catalog',
            static fn ($request) => $container->get(CatalogAdminController::class)->create($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/catalog',
            static fn ($request) => $container->get(CatalogArchiveController::class)($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/{locale:locale}/catalog',
            static fn ($request) => $container->get(CatalogArchiveController::class)($request),
        ));
        $kernel->registerAdminNavSection('catalog', [
            'paths' => ['/admin/catalog'],
            'pageTypes' => [\Nexis\Content\PageType::PRODUCT],
        ]);
        $kernel->registerAdminSlot('admin.nav', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.catalog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $active = (($ctx['activeAdminNav'] ?? '') === 'catalog') ? ' class="is-active"' : '';

            return '<a href="' . $base . '/admin/catalog"' . $active . '>' . $label . '</a>';
        });
        $kernel->registerAdminSlot('admin.dashboard.content', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.catalog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a class="btn btn-small" href="' . $base . '/admin/catalog">' . $label . '</a>';
        });
        $kernel->registerAdminSlot('theme.head', static function (array $ctx) use ($container): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $href = $container->get(PluginAssetRegistry::class)->url('nexis/catalog', 'product-details.css');

            return '<link rel="stylesheet" href="' . $base . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . "\n";
        });
        $kernel->registerSitemapPath(CatalogPaths::archivePath());
        $kernel->registerPrimaryNavLink(
            CatalogPaths::archivePath(),
            static fn (string $locale): string => $locale === 'en' ? 'Catalog' : 'Katalog',
        );
    }
}
