<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Http\RequestSecurity;
use Nexis\Http\ResponseFactory;
use Nexis\I18n\PublicUi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Simple file-based IP rate limit for sensitive POST endpoints.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactory $responses,
        private string $storagePath,
        private RequestSecurity $security,
        private PublicUi $ui,
        private int $maxAttempts = 30,
        private int $windowSeconds = 60,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method !== 'POST') {
            return $handler->handle($request);
        }

        $path = (string) $request->getAttribute('path', '/');
        $sensitive = str_starts_with($path, '/admin/login')
            || str_starts_with($path, '/account/login')
            || str_starts_with($path, '/account/register')
            || str_starts_with($path, '/account/password')
            || str_starts_with($path, '/ext/')
            || str_starts_with($path, '/preview/');
        if (!$sensitive) {
            return $handler->handle($request);
        }

        $max = $this->maxAttempts;
        $window = $this->windowSeconds;
        if (str_starts_with($path, '/account/password')) {
            $max = 8;
            $window = 600;
        }

        $ip = $this->security->clientIp($request);
        $bucket = $this->storagePath . DIRECTORY_SEPARATOR . hash('sha256', $ip . '|' . $path) . '.json';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }

        $now = time();
        $hits = [];
        if (is_file($bucket)) {
            $raw = file_get_contents($bucket);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if (is_int($ts) && $ts > $now - $window) {
                        $hits[] = $ts;
                    }
                }
            }
        }
        if (count($hits) >= $max) {
            return $this->responses->html($this->ui->getRequest($request, 'http.rate_limited'), 429)
                ->withHeader('Retry-After', (string) $window);
        }
        $hits[] = $now;
        file_put_contents($bucket, json_encode($hits, JSON_THROW_ON_ERROR));

        return $handler->handle($request);
    }
}
