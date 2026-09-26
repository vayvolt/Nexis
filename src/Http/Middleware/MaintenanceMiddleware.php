<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\PublicErrorRenderer;
use Nexis\I18n\PublicUi;
use Nexis\Site\MaintenanceMode;
use Nexis\Site\Site;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves a 503 maintenance page for public traffic while CMS staff can still work.
 */
final class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MaintenanceMode $maintenance,
        private PublicErrorRenderer $errors,
        private SitePolicy $policy,
        private PublicUi $ui,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site || !$this->maintenance->isEnabled($site->id)) {
            return $handler->handle($request);
        }

        $path = (string) $request->getAttribute('path', '/');
        if ($this->isBypassedPath($path)) {
            return $handler->handle($request);
        }

        $user = $request->getAttribute('user');
        if ($user instanceof User && $this->policy->canAccessCms($user, $site)) {
            return $handler->handle($request);
        }

        $custom = $this->maintenance->message($site->id);
        $locale = $site->defaultLocale;
        $message = $custom !== ''
            ? $custom
            : $this->ui->get($locale, 'public.maintenance.message');

        return $this->errors->maintenance($request, $message, $site, $locale);
    }

    private function isBypassedPath(string $path): bool
    {
        if ($path === '/health' || str_starts_with($path, '/health/')) {
            return true;
        }
        if (str_starts_with($path, '/admin')) {
            return true;
        }
        if (str_starts_with($path, '/assets/')) {
            return true;
        }
        // Published media URLs under /media keep working for CMS preview assets.
        if (str_starts_with($path, '/media/')) {
            return true;
        }

        return false;
    }
}
