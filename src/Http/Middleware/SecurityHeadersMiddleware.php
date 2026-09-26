<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Http\ContentSecurityPolicy;
use Nexis\Plugin\PluginKernel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PluginKernel $plugins,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        $csp = new ContentSecurityPolicy();
        foreach ($this->plugins->cspContributors() as $contributor) {
            $contributor->contribute($csp);
        }

        return $response
            ->withHeader('Content-Security-Policy', $csp->toHeaderValue())
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->withHeader('X-XSS-Protection', '0');
    }
}
