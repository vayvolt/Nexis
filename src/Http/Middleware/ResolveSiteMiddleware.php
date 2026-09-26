<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Event\EventDispatcher;
use Nexis\Event\SiteResolved;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves the installed site onto the request and dispatches SiteResolved once.
 */
final class ResolveSiteMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SiteRepository $sites,
        private EventDispatcher $events,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $site = $this->sites->installed();
        } catch (Throwable $e) {
            $this->logger?->warning('Site resolve failed', ['exception' => $e->getMessage()]);
            $site = null;
        }

        if ($site instanceof Site) {
            $request = $request->withAttribute('site', $site);
            try {
                $this->events->dispatch(new SiteResolved($site, $request));
            } catch (Throwable $e) {
                $this->logger?->error('SiteResolved listener failed', ['exception' => $e->getMessage()]);
            }
        }

        return $handler->handle($request);
    }
}
