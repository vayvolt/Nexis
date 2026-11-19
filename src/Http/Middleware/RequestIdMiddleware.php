<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Support\Uuid;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'request_id';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $incoming = trim($request->getHeaderLine('X-Request-Id'));
        $requestId = $incoming !== '' && Uuid::isValid($incoming) ? $incoming : Uuid::v7();

        $response = $handler->handle($request->withAttribute(self::ATTRIBUTE, $requestId));

        return $response->withHeader('X-Request-Id', $requestId);
    }
}
