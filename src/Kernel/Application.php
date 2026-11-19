<?php

declare(strict_types=1);

namespace Nexis\Kernel;

use Nexis\Http\HttpKernel;
use Nexis\Infrastructure\Database\Migrator;
use Nexis\Plugin\PluginRuntime;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Application
{
    public function __construct(
        public private(set) string $rootPath,
        public private(set) ContainerInterface $container,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Pending core SQL + ensure* only for Admin / Admin-API — not on the public site.
        if ($this->isAdminSurface($request)) {
            $this->container->get(Migrator::class)->migrate();
        }
        $this->container->get(PluginRuntime::class)->bootOnce();

        return $this->container->get(HttpKernel::class)->handle($request);
    }

    private function isAdminSurface(ServerRequestInterface $request): bool
    {
        $path = (string) $request->getAttribute('path', '');
        if ($path === '') {
            $path = $request->getUri()->getPath();
        }
        $path = '/' . ltrim($path, '/');

        return str_starts_with($path, '/admin')
            || str_starts_with($path, '/api/v1/admin');
    }
}
