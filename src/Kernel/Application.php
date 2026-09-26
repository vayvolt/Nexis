<?php

declare(strict_types=1);

namespace Nexis\Kernel;

use Nexis\Http\HttpKernel;
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
        $this->container->get(PluginRuntime::class)->bootOnce();

        return $this->container->get(HttpKernel::class)->handle($request);
    }
}
