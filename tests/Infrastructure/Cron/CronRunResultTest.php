<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure\Cron;

use Nexis\Infrastructure\Cron\CronRunResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CronRunResultTest extends TestCase
{
    #[Test]
    public function ok_result_exposes_lines(): void
    {
        $result = CronRunResult::ok(['done']);
        self::assertTrue($result->ok);
        self::assertFalse($result->busy);
        self::assertNull($result->error);
        self::assertSame(['done'], $result->lines);
    }

    #[Test]
    public function busy_and_failed_flags(): void
    {
        $busy = CronRunResult::busy();
        self::assertTrue($busy->busy);
        self::assertFalse($busy->ok);

        $failed = CronRunResult::failed('boom', ['partial']);
        self::assertFalse($failed->ok);
        self::assertSame('boom', $failed->error);
        self::assertSame(['partial'], $failed->lines);
    }
}
