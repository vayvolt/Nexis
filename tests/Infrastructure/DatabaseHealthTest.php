<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure;

use Nexis\Infrastructure\Database\DatabaseHealth;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseHealthTest extends TestCase
{
    public function testDownWhenConnectorFails(): void
    {
        $health = new DatabaseHealth(static function (): PDO {
            throw new RuntimeException('unreachable');
        });

        self::assertSame('down', $health->status());
    }
}
