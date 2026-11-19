<?php

declare(strict_types=1);

namespace Nexis\Plugins\Blog;

use Nexis\Http\Route;
use Nexis\Plugin\PluginKernel;
use Nexis\Plugin\PluginServiceProvider;
use Psr\Container\ContainerInterface;

final class BlogServiceProvider implements PluginServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(PluginKernel $kernel, ContainerInterface $container): void
    {
        $kernel->registerBlock($container->get(BlogPostsBlock::class));
        // Admin routes BEFORE /{locale}/blog — otherwise locale="admin" steals /admin/blog.
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/blog',
            static fn ($request) => $container->get(BlogAdminController::class)->index($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/blog/new',
            static fn ($request) => $container->get(BlogAdminController::class)->createForm($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/blog',
            static fn ($request) => $container->get(BlogAdminController::class)->create($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/blog',
            static fn ($request) => $container->get(BlogArchiveController::class)($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/{locale:locale}/blog',
            static fn ($request) => $container->get(BlogArchiveController::class)($request),
        ));
        $kernel->registerAdminNavSection('blog', [
            'paths' => ['/admin/blog'],
            'pageTypes' => [\Nexis\Content\PageType::POST],
        ]);
        $kernel->registerAdminSlot('admin.nav', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $active = (($ctx['activeAdminNav'] ?? '') === 'blog') ? ' class="is-active"' : '';

            return '<a href="' . $base . '/admin/blog"' . $active . '>' . $label . '</a>';
        });
        $kernel->registerAdminSlot('admin.dashboard.content', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.blog'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a class="btn btn-small" href="' . $base . '/admin/blog">' . $label . '</a>';
        });
        $kernel->registerSitemapPath(BlogPaths::archivePath());
        $kernel->registerPrimaryNavLink(
            BlogPaths::archivePath(),
            static fn (string $_locale): string => 'Blog',
        );
    }
}
