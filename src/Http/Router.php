<?php

declare(strict_types=1);

namespace Nexis\Http;

use Closure;
use Nexis\I18n\PublicUi;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Router implements RequestHandlerInterface
{
    /**
     * @param callable(ServerRequestInterface): ResponseInterface|null $fallback
     */
    public function __construct(
        private ResponseFactory $responses,
        private RouteCollector $routes,
        private PublicUi $ui,
        private mixed $fallback = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path = self::normalizePath((string) $request->getAttribute('path', $request->getUri()->getPath()));

        foreach ($this->routes->all() as $route) {
            $params = $this->match($route, $method, $path);
            if ($params === null) {
                continue;
            }

            $requestWithParams = $request;
            foreach ($params as $key => $value) {
                $requestWithParams = $requestWithParams->withAttribute($key, $value);
            }

            $handler = $route->handler;
            if ($handler instanceof Closure || is_callable($handler)) {
                return $handler($requestWithParams);
            }
        }

        if (is_callable($this->fallback)) {
            return ($this->fallback)($request);
        }

        $requestId = (string) $request->getAttribute('request_id', '');

        return $this->responses->json([
            'error' => [
                'code' => 'http.not_found',
                'message' => $this->ui->getRequest($request, 'http.not_found'),
                'details' => new \stdClass(),
            ],
            'requestId' => $requestId,
        ], 404)->withHeader('X-Request-Id', $requestId);
    }

    /**
     * @return array<string, string>|null
     */
    private function match(Route $route, string $method, string $path): ?array
    {
        $routeMethod = strtoupper($route->method);
        if ($routeMethod !== $method && !($method === 'HEAD' && $routeMethod === 'GET')) {
            return null;
        }

        $regex = $this->patternToRegex($route->pattern);
        if (preg_match('#^' . $regex . '$#', $path, $matches) !== 1) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function patternToRegex(string $pattern): string
    {
        $out = '';
        $len = strlen($pattern);
        for ($i = 0; $i < $len; $i++) {
            if ($pattern[$i] !== '{') {
                $out .= preg_quote($pattern[$i], '#');
                continue;
            }
            $end = $this->findClosingBrace($pattern, $i);
            if ($end === null) {
                $out .= preg_quote($pattern[$i], '#');
                continue;
            }
            $token = substr($pattern, $i + 1, $end - $i - 1);
            $name = $token;
            $constraint = '[^/]+';
            $colon = strpos($token, ':');
            if ($colon !== false) {
                $name = substr($token, 0, $colon);
                $raw = substr($token, $colon + 1);
                $constraint = self::CONSTRAINT_ALIASES[$raw] ?? $raw;
            }
            $out .= '(?P<' . $name . '>' . $constraint . ')';
            $i = $end;
        }

        return $out;
    }

    private function findClosingBrace(string $pattern, int $open): ?int
    {
        $depth = 0;
        $len = strlen($pattern);
        for ($i = $open; $i < $len; $i++) {
            $ch = $pattern[$i];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /** @var array<string, string> */
    private const CONSTRAINT_ALIASES = [
        'locale' => '[a-z]{2}(?:-[A-Z]{2})?',
    ];

    public static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        return $path;
    }
}
