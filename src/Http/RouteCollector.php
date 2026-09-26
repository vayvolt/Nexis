<?php

declare(strict_types=1);

namespace Nexis\Http;

/**
 * Mutable route list so plugins can register routes at boot.
 */
final class RouteCollector
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @param list<Route> $routes
     */
    public function __construct(array $routes = [])
    {
        foreach ($routes as $route) {
            $this->add($route);
        }
    }

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /**
     * @return list<Route>
     */
    public function all(): array
    {
        return $this->routes;
    }
}
