<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\ContentSecurityPolicy;
use Nexis\Http\RequestSecurity;
use Nexis\Kernel\Config;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RequestSecurityTest extends TestCase
{
    public function testLocalHostDetection(): void
    {
        self::assertTrue(RequestSecurity::isLocalHost('localhost'));
        self::assertTrue(RequestSecurity::isLocalHost('127.0.0.1'));
        self::assertTrue(RequestSecurity::isLocalHost('site.local'));
        self::assertTrue(RequestSecurity::isLocalHost('demo.test'));
        self::assertFalse(RequestSecurity::isLocalHost('example.com'));
    }

    public function testHttpsFromAppUrl(): void
    {
        $_ENV['APP_URL'] = 'https://example.com';
        $_ENV['TRUSTED_PROXIES'] = '';
        $config = Config::load(sys_get_temp_dir());
        $security = new RequestSecurity($config);
        $request = new ServerRequest('GET', 'http://example.com/');

        self::assertTrue($security->isHttps($request));
        unset($_ENV['APP_URL'], $_ENV['TRUSTED_PROXIES']);
    }

    public function testHttpsFromTrustedProxyHeader(): void
    {
        $_ENV['APP_URL'] = 'http://example.com';
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $config = Config::load(sys_get_temp_dir());
        $security = new RequestSecurity($config);
        $request = new ServerRequest(
            'GET',
            'http://example.com/',
            [],
            null,
            '1.1',
            [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );

        self::assertTrue($security->isHttps($request));
        unset($_ENV['APP_URL'], $_ENV['TRUSTED_PROXIES']);
    }

    public function testClientIpUsesForwardedForFromTrustedProxy(): void
    {
        $_ENV['APP_URL'] = 'http://example.com';
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $config = Config::load(sys_get_temp_dir());
        $security = new RequestSecurity($config);
        $request = new ServerRequest(
            'GET',
            'http://example.com/',
            [],
            null,
            '1.1',
            [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 10.0.0.1',
            ],
        );

        self::assertSame('203.0.113.50', $security->clientIp($request));
        unset($_ENV['APP_URL'], $_ENV['TRUSTED_PROXIES']);
    }

    public function testSameSiteFormPostRequiresOriginOrReferer(): void
    {
        $_ENV['APP_URL'] = 'http://example.com';
        $config = Config::load(sys_get_temp_dir());
        $security = new RequestSecurity($config);
        $request = new ServerRequest('POST', 'http://example.com/ext/forms/submit');
        self::assertFalse($security->isSameSiteFormPost($request));

        $withOrigin = $request->withHeader('Origin', 'http://example.com');
        self::assertTrue($security->isSameSiteFormPost($withOrigin));

        $evil = $request->withHeader('Origin', 'https://evil.example');
        self::assertFalse($security->isSameSiteFormPost($evil));

        $withReferer = $request->withHeader('Referer', 'http://example.com/de/kontakt');
        self::assertTrue($security->isSameSiteFormPost($withReferer));
        self::assertSame('http://example.com/de/kontakt', $security->sameSiteReferer($withReferer));
        self::assertNull($security->sameSiteReferer($evil->withHeader('Referer', 'https://evil.example/x')));
        unset($_ENV['APP_URL']);
    }

    public function testIgnoresForwardedProtoFromUntrustedClient(): void
    {
        $_ENV['APP_URL'] = 'http://example.com';
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1';
        $config = Config::load(sys_get_temp_dir());
        $security = new RequestSecurity($config);
        $request = new ServerRequest(
            'GET',
            'http://example.com/',
            [],
            null,
            '1.1',
            [
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ],
        );
        self::assertFalse($security->isHttps($request));
        unset($_ENV['APP_URL'], $_ENV['TRUSTED_PROXIES']);
    }
}

final class ContentSecurityPolicyTest extends TestCase
{
    public function testDefaultHeaderIsStrictOnScripts(): void
    {
        $header = (new ContentSecurityPolicy())->toHeaderValue();
        self::assertStringContainsString("script-src 'self'", $header);
        self::assertStringNotContainsString('googletagmanager', $header);
    }

    public function testAllowScriptMergesSources(): void
    {
        $csp = new ContentSecurityPolicy();
        $csp->allowScript("'unsafe-inline'", 'https://www.googletagmanager.com');
        $header = $csp->toHeaderValue();
        self::assertStringContainsString("'unsafe-inline'", $header);
        self::assertStringContainsString('https://www.googletagmanager.com', $header);
    }
}
