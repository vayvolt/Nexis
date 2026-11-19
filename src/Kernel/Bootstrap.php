<?php

declare(strict_types=1);

namespace Nexis\Kernel;

use DI\ContainerBuilder;

final class Bootstrap
{
    public static function boot(string $rootPath): Application
    {
        $rootPath = rtrim($rootPath, '\\/');
        Env::load($rootPath);

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions(['app.root' => $rootPath]);
        $builder->addDefinitions($rootPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'container.php');

        return new Application($rootPath, $builder->build());
    }
}
