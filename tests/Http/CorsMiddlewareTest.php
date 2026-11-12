<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\Middleware\CorsMiddleware;
use Nexis\Http\ResponseFactory;
use Nexis\Kernel\Config;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddlewareTest extends TestCase
{
    public function testSkipsWhenAllowlistEmpty(): void
    {
        $middleware = $this->middleware('');
        $handler = $this->handler(200, ['X-Test' => '1']);

        $response = $middleware->process(
            (new ServerRequest('GET', '/api/v1/public/pages'))
                ->withAttribute('path', '/api/v1/public/pages')
                ->withHeader('Origin', 'https://app.example'),
            $handler,
        );

        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
        self::assertSame('1', $response->getHeaderLine('X-Test'));
    }

    public function testAddsHeadersForAllowedOrigin(): void
    {
        $middleware = $this->middleware('https://app.example, https://other.example');
        $handler = $this->handler(200);

        $response = $middleware->process(
            (new ServerRequest('GET', '/api/v1/public/pages'))
                ->withAttribute('path', '/api/v1/public/pages')
                ->withHeader('Origin', 'https://app.example'),
            $handler,
        );

        self::assertSame('https://app.example', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('X-Request-Id, ETag, Location', $response->getHeaderLine('Access-Control-Expose-Headers'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }

    public function testRejectsDisallowedOriginWithoutAllowOrigin(): void
    {
        $middleware = $this->middleware('https://app.example');
        $handler = $this->handler(200);

        $response = $middleware->process(
            (new ServerRequest('GET', '/api/v1/public/pages'))
                ->withAttribute('path', '/api/v1/public/pages')
                ->withHeader('Origin', 'https://evil.example'),
            $handler,
        );

        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    public function testOptionsPreflightShortCircuits(): void
    {
        $middleware = $this->middleware('https://app.example');
        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;

                return new Response(200, [], 'should-not-run');
            }
        };

        $response = $middleware->process(
            (new ServerRequest('OPTIONS', '/api/v1/admin/pages'))
                ->withAttribute('path', '/api/v1/admin/pages')
                ->withHeader('Origin', 'https://app.example'),
            $handler,
        );

        self::assertFalse($handler->called);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('https://app.example', $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertStringContainsString('Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testWildcardAllowsAnyOrigin(): void
    {
        $middleware = $this->middleware('*');
        $handler = $this->handler(200);

        $response = $middleware->process(
            (new ServerRequest('GET', '/api/v1/public/sites/current'))
                ->withAttribute('path', '/api/v1/public/sites/current')
                ->withHeader('Origin', 'https://random.example'),
            $handler,
        );

        self::assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testIgnoresNonApiPaths(): void
    {
        $middleware = $this->middleware('*');
        $handler = $this->handler(200);

        $response = $middleware->process(
            (new ServerRequest('GET', '/admin/backups'))
                ->withAttribute('path', '/admin/backups')
                ->withHeader('Origin', 'https://app.example'),
            $handler,
        );

        self::assertFalse($response->hasHeader('Access-Control-Allow-Origin'));
    }

    private function middleware(string $origins): CorsMiddleware
    {
        $psr17 = new Psr17Factory();
        $config = new Config(['api' => ['cors_origins' => $origins]], sys_get_temp_dir());

        return new CorsMiddleware($config, new ResponseFactory($psr17, $psr17));
    }

    /**
     * @param array<string, string> $headers
     */
    private function handler(int $status, array $headers = []): RequestHandlerInterface
    {
        return new class ($status, $headers) implements RequestHandlerInterface {
            /**
             * @param array<string, string> $headers
             */
            public function __construct(private int $status, private array $headers)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response($this->status, $this->headers, 'ok');
            }
        };
    }
}
