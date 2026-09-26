<?php

declare(strict_types=1);

namespace Nexis\Kernel;

use Dotenv\Dotenv;

final class Env
{
    public static function load(string $rootPath): void
    {
        $file = $rootPath . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($file)) {
            return;
        }

        Dotenv::createImmutable($rootPath)->safeLoad();
    }
}
