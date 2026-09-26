<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    #[DataProvider('paths')]
    public function testNormalizePath(string $input, string $expected): void
    {
        self::assertSame($expected, Router::normalizePath($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function paths(): array
    {
        return [
            'root' => ['/', '/'],
            'empty' => ['', '/'],
            'trailing' => ['/health/', '/health'],
            'nested' => ['/api/v1/public', '/api/v1/public'],
        ];
    }
}
