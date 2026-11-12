<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Http\ResponseFactory;
use Nexis\Kernel\Config;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Optional CORS for `/api/v1/*` when `API_CORS_ORIGINS` is set.
 * Empty allowlist keeps the previous same-origin-only behaviour.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    private const API_PREFIX = '/api/v1';

    public function __construct(
        private Config $config,
        private ResponseFactory $responses,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = (string) $request->getAttribute('path', $request->getUri()->getPath());
        if (!$this->isApiPath($path)) {
            return $handler->handle($request);
        }

        $allowlist = $this->config->apiCorsOrigins();
        if ($allowlist === []) {
            return $handler->handle($request);
        }

        $origin = trim($request->getHeaderLine('Origin'));
        $allowedOrigin = $this->resolveAllowedOrigin($origin, $allowlist);

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withCorsHeaders($this->responses->empty(204), $allowedOrigin);
        }

        $response = $handler->handle($request);

        return $allowedOrigin !== null
            ? $this->withCorsHeaders($response, $allowedOrigin)
            : $response;
    }

    private function isApiPath(string $path): bool
    {
        return $path === self::API_PREFIX || str_starts_with($path, self::API_PREFIX . '/');
    }

    /**
     * @param list<string> $allowlist
     */
    private function resolveAllowedOrigin(string $origin, array $allowlist): ?string
    {
        if ($origin === '') {
            return null;
        }
        if (in_array('*', $allowlist, true)) {
            return '*';
        }
        foreach ($allowlist as $allowed) {
            if (strcasecmp($allowed, $origin) === 0) {
                return $origin;
            }
        }

        return null;
    }

    private function withCorsHeaders(ResponseInterface $response, ?string $allowedOrigin): ResponseInterface
    {
        if ($allowedOrigin === null) {
            // Preflight without a matching Origin still advertises methods/headers
            // so browsers can surface a clear CORS error instead of a network failure.
            return $response
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader(
                    'Access-Control-Allow-Headers',
                    'Authorization, Content-Type, If-Match, X-CSRF-Token, Idempotency-Key, X-Request-Id',
                )
                ->withHeader('Access-Control-Max-Age', '86400')
                ->withHeader('Vary', 'Origin');
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader(
                'Access-Control-Allow-Headers',
                'Authorization, Content-Type, If-Match, X-CSRF-Token, Idempotency-Key, X-Request-Id',
            )
            ->withHeader('Access-Control-Expose-Headers', 'X-Request-Id, ETag, Location')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Vary', 'Origin');
    }
}
