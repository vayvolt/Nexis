<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Event\EventDispatcher;
use Nexis\Event\SiteResolved;
use Nexis\Http\Middleware\ResolveSiteMiddleware;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;
use Nexis\Site\TenantId;
use Nexis\Support\Uuid;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ResolveSiteMiddlewareTest extends TestCase
{
    public function testDispatchesSiteResolvedAndSetsAttribute(): void
    {
        $site = new Site(
            new SiteId(Uuid::v7()),
            new TenantId(Uuid::v7()),
            'Demo',
            'localhost',
            'de',
            LocaleUrlStrategy::None,
            [],
        );
        $sites = $this->createStub(SiteRepository::class);
        $sites->method('installed')->willReturn($site);

        $seen = null;
        $events = new EventDispatcher();
        $events->listen(SiteResolved::class, static function (SiteResolved $event) use (&$seen): void {
            $seen = $event;
        });

        $middleware = new ResolveSiteMiddleware($sites, $events);
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(200, [], 'ok');
            }
        };

        $middleware->process(new ServerRequest('GET', '/'), $handler);

        self::assertInstanceOf(SiteResolved::class, $seen);
        self::assertSame($site, $seen->site);
        self::assertSame($site, $handler->request?->getAttribute('site'));
    }

    public function testSkipsWhenNoSiteInstalled(): void
    {
        $sites = $this->createStub(SiteRepository::class);
        $sites->method('installed')->willReturn(null);
        $fired = false;
        $events = new EventDispatcher();
        $events->listen(SiteResolved::class, static function () use (&$fired): void {
            $fired = true;
        });

        $middleware = new ResolveSiteMiddleware($sites, $events);
        $handler = new class implements RequestHandlerInterface {
            public mixed $seenSite = 'unset';

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seenSite = $request->getAttribute('site');

                return new Response(200, [], 'ok');
            }
        };

        $middleware->process(new ServerRequest('GET', '/'), $handler);
        self::assertFalse($fired);
        self::assertNull($handler->seenSite);
    }
}
