<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Http\Route;
use Nexis\Plugin\PluginKernel;
use Nexis\Plugin\PluginServiceProvider;
use Psr\Container\ContainerInterface;

final class FormsServiceProvider implements PluginServiceProvider
{
    public function register(ContainerInterface $container): void
    {
    }

    public function boot(PluginKernel $kernel, ContainerInterface $container): void
    {
        $kernel->registerSettings([
            'notify_email' => [
                'type' => 'email',
                'default' => '',
                'label' => 'admin.forms.notify_email',
            ],
        ]);
        $kernel->registerMailJobHandler(
            'form_submission',
            $container->get(FormSubmissionMailHandler::class),
        );
        $kernel->registerBlock($container->get(ContactFormBlock::class));
        $kernel->registerRoute(new Route(
            'POST',
            '/ext/nexis/forms/submit',
            static fn ($request) => $container->get(SubmitController::class)($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/forms',
            static fn ($request) => $container->get(SubmissionsController::class)->index($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/forms/read',
            static fn ($request) => $container->get(SubmissionsController::class)->markRead($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/forms/read-all',
            static fn ($request) => $container->get(SubmissionsController::class)->markAllRead($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/forms/delete',
            static fn ($request) => $container->get(SubmissionsController::class)->delete($request),
        ));
        $kernel->registerRoute(new Route(
            'GET',
            '/admin/forms/export',
            static fn ($request) => $container->get(SubmissionsController::class)->export($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/forms/settings',
            static fn ($request) => $container->get(SubmissionsController::class)->saveSettings($request),
        ));
        $kernel->registerRoute(new Route(
            'POST',
            '/admin/forms/test-mail',
            static fn ($request) => $container->get(SubmissionsController::class)->testMail($request),
        ));
        $kernel->registerAdminNavSection('forms', [
            'paths' => ['/admin/forms'],
        ]);
        $kernel->registerAdminSlot('admin.nav', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.forms'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $active = (($ctx['activeAdminNav'] ?? '') === 'forms') ? ' class="is-active"' : '';

            return '<a href="' . $base . '/admin/forms"' . $active . '>' . $label . '</a>';
        });
        $kernel->registerAdminSlot('admin.dashboard.content', static function (array $ctx): string {
            $base = htmlspecialchars((string) ($ctx['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $t = $ctx['t'] ?? static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
            $label = htmlspecialchars((string) $t('admin.nav.forms'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<a class="btn btn-small" href="' . $base . '/admin/forms">' . $label . '</a>';
        });
    }
}
