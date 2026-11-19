<?php

declare(strict_types=1);

namespace Nexis\Tests\Install;

use Nexis\Install\DatabasePreflight;
use PHPUnit\Framework\TestCase;

final class DatabasePreflightTest extends TestCase
{
    public function testInvalidDatabaseName(): void
    {
        $result = (new DatabasePreflight())->verify('127.0.0.1', '3306', 'bad-name!', 'root', '');

        self::assertFalse($result->ok);
        self::assertSame('invalid_name', $result->code);
    }

    public function testEmptyHostFailsConnection(): void
    {
        $result = (new DatabasePreflight())->verify('', '3306', 'nexis', 'root', '');

        self::assertFalse($result->ok);
        self::assertSame('connection', $result->code);
    }

    public function testUnreachablePortFailsConnection(): void
    {
        $result = (new DatabasePreflight())->verify('127.0.0.1', '59999', 'nexis', 'root', 'x');

        self::assertFalse($result->ok);
        self::assertSame('connection', $result->code);
        self::assertNotSame('', $result->detail);
    }
}
