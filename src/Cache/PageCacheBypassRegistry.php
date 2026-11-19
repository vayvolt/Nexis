<?php

declare(strict_types=1);

namespace Nexis\Cache;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Plugin-registered checkers that skip full-page HTML cache for a request.
 */
final class PageCacheBypassRegistry
{
    /** @var list<callable(ServerRequestInterface): bool> */
    private array $checkers = [];

    /**
     * @param callable(ServerRequestInterface): bool $checker
     */
    public function register(callable $checker): void
    {
        $this->checkers[] = $checker;
    }

    public function shouldBypass(ServerRequestInterface $request): bool
    {
        foreach ($this->checkers as $checker) {
            if ($checker($request)) {
                return true;
            }
        }

        return false;
    }
}
