<?php

declare(strict_types=1);

namespace Nexis\Tests\Kernel;

use Nexis\Kernel\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDotAccessAndDebugFlag(): void
    {
        $_ENV['APP_ENV'] = 'local';
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['DB_DATABASE'] = 'nexis_test';

        $config = Config::load(sys_get_temp_dir());

        self::assertSame('local', $config->envName());
        self::assertTrue($config->debug());
        self::assertSame('nexis_test', $config->get('db.database'));
        self::assertSame('fallback', $config->get('missing.key', 'fallback'));

        unset($_ENV['APP_ENV'], $_ENV['APP_DEBUG'], $_ENV['DB_DATABASE']);
    }

    public function testTrustedProxiesParsing(): void
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1, 10.0.0.2';
        $config = Config::load(sys_get_temp_dir());
        self::assertSame(['10.0.0.1', '10.0.0.2'], $config->trustedProxies());
        unset($_ENV['TRUSTED_PROXIES']);
    }

    public function testMediaLimitsDefaults(): void
    {
        $config = Config::load(sys_get_temp_dir());
        self::assertSame(10_485_760, $config->get('media.max_bytes'));
        self::assertSame(25_000_000, $config->get('media.max_pixels'));
    }

    public function testPublicBasePathFromAppUrl(): void
    {
        $_ENV['APP_URL'] = 'http://localhost/nexis';
        $config = Config::load(sys_get_temp_dir());
        self::assertSame('/nexis', $config->publicBasePath());
        unset($_ENV['APP_URL']);

        $_ENV['APP_URL'] = 'https://example.com/';
        $config = Config::load(sys_get_temp_dir());
        self::assertSame('', $config->publicBasePath());
        unset($_ENV['APP_URL']);
    }
}
