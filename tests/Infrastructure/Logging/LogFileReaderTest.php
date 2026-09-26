<?php

declare(strict_types=1);

namespace Nexis\Tests\Infrastructure\Logging;

use Nexis\Infrastructure\Logging\LogFileReader;
use Nexis\Kernel\Config;
use PHPUnit\Framework\TestCase;

final class LogFileReaderTest extends TestCase
{
    public function testRecentParsesJsonAndFiltersLevel(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-logs-' . bin2hex(random_bytes(4));
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        mkdir($dir, 0775, true);
        $path = $dir . DIRECTORY_SEPARATOR . 'app.log';
        $lines = [
            json_encode(['datetime' => '2026-01-01T10:00:00+00:00', 'level_name' => 'INFO', 'message' => 'hello', 'channel' => 'nexis', 'context' => []], JSON_THROW_ON_ERROR),
            json_encode(['datetime' => '2026-01-01T10:01:00+00:00', 'level_name' => 'ERROR', 'message' => 'boom password=secret123', 'channel' => 'nexis', 'context' => ['token' => 'abc']], JSON_THROW_ON_ERROR),
            json_encode(['datetime' => '2026-01-01T10:02:00+00:00', 'level_name' => 'WARNING', 'message' => 'warn', 'channel' => 'nexis', 'context' => []], JSON_THROW_ON_ERROR),
        ];
        file_put_contents($path, implode("\n", $lines) . "\n");

        try {
            $reader = new LogFileReader(new Config([], $root));
            $all = $reader->recent(10);
            self::assertCount(3, $all);
            self::assertSame('WARNING', $all[0]['level']);

            $errors = $reader->recent(10, 'ERROR');
            self::assertCount(1, $errors);
            self::assertStringContainsString('password=***', $errors[0]['message']);
            self::assertStringContainsString('***', $errors[0]['context']);
        } finally {
            @unlink($path);
            @rmdir($dir);
            @rmdir(dirname($dir));
            @rmdir($root);
        }
    }
}
