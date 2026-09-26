<?php

declare(strict_types=1);

namespace Nexis\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpKernel implements RequestHandlerInterface
{
    public function __construct(
        private MiddlewarePipeline $pipeline,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->pipeline->handle($request);
    }
}
