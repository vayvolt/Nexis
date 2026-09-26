<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Psr\Container\ContainerInterface;

interface PluginServiceProvider
{
    public function register(ContainerInterface $container): void;

    public function boot(PluginKernel $kernel, ContainerInterface $container): void;
}
