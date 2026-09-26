<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Http\Csrf;
use Nexis\Http\CsrfExemptRegistry;
use Nexis\Http\ResponseFactory;
use Nexis\I18n\PublicUi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Csrf $csrf,
        private ResponseFactory $responses,
        private PublicUi $ui,
        private CsrfExemptRegistry $exempt,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $path = (string) $request->getAttribute('path', $request->getUri()->getPath());
        if ($this->exempt->isExempt($path)) {
            return $handler->handle($request);
        }

        $needsCsrf = str_starts_with($path, '/admin')
            || str_starts_with($path, '/account')
            || str_starts_with($path, '/api/v1/admin')
            || str_starts_with($path, '/ext/');
        if (!$needsCsrf) {
            return $handler->handle($request);
        }

        $body = $request->getParsedBody();
        $token = is_array($body) ? ($body[Csrf::FIELD] ?? null) : null;
        if (!is_string($token) || $token === '') {
            $token = $request->getHeaderLine('X-CSRF-Token');
        }

        try {
            $this->csrf->assert($token);
        } catch (RuntimeException) {
            return $this->responses->html($this->ui->getRequest($request, 'http.csrf_invalid'), 419);
        }

        return $handler->handle($request);
    }
}
