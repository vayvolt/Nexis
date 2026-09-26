<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\ResponseFactory;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = (string) $request->getAttribute('path', '/');
        $needsAuth = str_starts_with($path, '/admin')
            && !str_starts_with($path, '/admin/login');
        if (!$needsAuth) {
            return $handler->handle($request);
        }

        $base = (string) $request->getAttribute('base_path', '');
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            return $this->responses->redirect($base . '/admin/login');
        }

        $site = $this->sites->installed();
        if ($site !== null && !$this->policy->canAccessCms($user, $site)) {
            return $this->responses->redirect($base . '/account');
        }

        return $handler->handle($request);
    }
}
