<?php

declare(strict_types=1);

use Nexis\Infrastructure\Database\Migrator;
use Nexis\Kernel\Bootstrap;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = Bootstrap::boot(dirname(__DIR__));
$migrator = $app->container->get(Migrator::class);
$applied = $migrator->migrate();

if ($applied === []) {
    fwrite(STDOUT, "nothing to migrate\n");
    exit(0);
}

foreach ($applied as $name) {
    fwrite(STDOUT, "applied {$name}\n");
}
