<?php

declare(strict_types=1);

namespace Nexis\Tests\Support;

use Nexis\Kernel\Application;
use Nexis\Kernel\Bootstrap;
use PDO;

trait BootsApplication
{
    protected function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function bootApp(): Application
    {
        try {
            $app = Bootstrap::boot($this->projectRoot());
            $app->container->get(PDO::class)->query('SELECT 1');

            return $app;
        } catch (\Throwable $e) {
            self::markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }
}
