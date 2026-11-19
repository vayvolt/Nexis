<?php

declare(strict_types=1);

namespace Nexis\Tests\Support;

use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testV7IsRfc4122Shaped(): void
    {
        $uuid = Uuid::v7();

        self::assertTrue(Uuid::isValid($uuid));
        self::assertSame('7', $uuid[14]);
    }

    public function testRejectsInvalidValues(): void
    {
        self::assertFalse(Uuid::isValid('not-a-uuid'));
        self::assertFalse(Uuid::isValid('00000000-0000-4000-8000-000000000000'));
    }
}
