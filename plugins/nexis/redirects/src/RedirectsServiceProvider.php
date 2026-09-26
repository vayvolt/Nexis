<?php

declare(strict_types=1);

namespace Nexis\Plugins\Redirects;

use Nexis\Event\PublicNotFound;
use Nexis\Http\Route;
use Nexis\Plugin\PluginKernel;
use Nexis\Plugin\PluginServiceProvider;
use Psr\Container\ContainerInterface;

final class RedirectsServiceProvider implements PluginServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(PluginKernel $kernel, ContainerInterface $container): void
    {
        $kernel->registerPreRoute($container->get(RedirectPreRoute::class));
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/redirects',
            static fn ($request) => $container->get(RedirectsAdminController::class)->index($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/redirects',
            static fn ($request) => $container->get(RedirectsAdminController::class)->create($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/redirects/update',
            static fn ($request) => $container->get(RedirectsAdminController::class)->update($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/redirects/import',
            static fn ($request) => $container->get(RedirectsAdminController::class)->importCsv($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/redirects/export.csv',
            static fn ($request) => $container->get(RedirectsAdminController::class)->exportCsv($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/redirects/delete',
            static fn ($request) => $container->get(RedirectsAdminController::class)->delete($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/redirects/not-found/delete',
            static fn ($request) => $container->get(RedirectsAdminController::class)->deleteNotFound($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/redirects/not-found/clear',
            static fn ($request) => $container->get(RedirectsAdminController::class)->clearNotFound($request),
        ));
        $kernel->registerAdminNavSection('redirects', [
            'paths' => ['/admin/redirects'],
        ]);
        $kernel->registerAdminSlot('admin.nav', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.redirects'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $active = (($ctx['activeAdminNav'] ?? '') === 'redirects') ? ' class="is-active"' : '';

            return '<a href="' . $base . '/admin/redirects"' . $active . '>' . $label . '</a>';
        });
        $kernel->registerAdminSlot('admin.dashboard.site', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.redirects'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a class="btn btn-small" href="' . $base . '/admin/redirects">' . $label . '</a>';
        });
        $kernel->listen(
            PublicNotFound::class,
            $container->get(NotFoundHitLogger::class)->onPublicNotFound(...),
        );
    }
}
