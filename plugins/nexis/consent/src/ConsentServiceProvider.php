<?php

declare(strict_types=1);

namespace Nexis\Plugins\Consent;

use Nexis\Http\Route;
use Nexis\Plugin\PluginKernel;
use Nexis\Plugin\PluginServiceProvider;
use Psr\Container\ContainerInterface;

final class ConsentServiceProvider implements PluginServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(PluginKernel $kernel, ContainerInterface $container): void
    {
        $kernel->registerSettings([
            'enabled' => ['type' => 'bool', 'default' => true],
            'privacy_url' => ['type' => 'url', 'default' => ''],
            'ga_measurement_id' => ['type' => 'string', 'default' => ''],
            'gtm_id' => ['type' => 'string', 'default' => ''],
            'google_ads_id' => ['type' => 'string', 'default' => ''],
        ]);
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/consent',
            static fn ($request) => $container->get(ConsentAdminController::class)->index($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/consent',
            static fn ($request) => $container->get(ConsentAdminController::class)->save($request),
        ));
        $kernel->registerAdminNavSection('consent', [
            'paths' => ['/admin/consent'],
        ]);
        $kernel->registerAdminSlot('admin.nav', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.consent'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $active = (($ctx['activeAdminNav'] ?? '') === 'consent') ? ' class="is-active"' : '';

            return '<a href="' . $base . '/admin/consent"' . $active . '>' . $label . '</a>';
        });
        $kernel->registerAdminSlot('admin.dashboard.site', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.consent'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a class="btn btn-small" href="' . $base . '/admin/consent">' . $label . '</a>';
        });
        $kernel->registerAdminSlot('theme.head', static function (array $ctx) use ($container): string {
            return $container->get(ConsentBanner::class)->headHtml($ctx);
        });
        $kernel->registerAdminSlot('theme.footer-links', static function (array $ctx) use ($container): string {
            return $container->get(ConsentBanner::class)->settingsLinkHtml($ctx);
        });
        $kernel->registerAdminSlot('theme.footer', static function (array $ctx) use ($container): string {
            return $container->get(ConsentBanner::class)->bannerHtml($ctx);
        });
        $kernel->registerCspContributor($container->get(ConsentCspContributor::class));
    }
}
