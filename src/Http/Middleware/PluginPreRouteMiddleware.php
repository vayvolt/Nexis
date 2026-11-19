<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Plugin\PluginKernel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PluginPreRouteMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PluginKernel $plugins,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach ($this->plugins->preRouteHandlers() as $pre) {
            $response = $pre->handle($request);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }

        return $handler->handle($request);
    }
}
