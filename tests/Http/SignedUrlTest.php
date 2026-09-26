<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\SignedUrl;
use Nexis\Kernel\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SignedUrlTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $config = new Config(['app' => ['key' => 'test-secret-key']], sys_get_temp_dir());
        $signer = new SignedUrl($config);
        $url = $signer->sign('/preview/abc', [], 60);
        self::assertStringContainsString('/preview/abc?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
        /** @var array<string, string> $params */
        self::assertArrayHasKey('sig', $params);
        self::assertArrayHasKey('expires', $params);
        $signer->assertValid('/preview/abc', $params);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $params['sig']);
    }

    public function testRejectsTampering(): void
    {
        $config = new Config(['app' => ['key' => 'test-secret-key']], sys_get_temp_dir());
        $signer = new SignedUrl($config);
        $url = $signer->sign('/preview/abc', [], 60);
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
        $params['sig'] = 'deadbeef';
        $this->expectException(RuntimeException::class);
        /** @var array<string, string> $params */
        $signer->assertValid('/preview/abc', $params);
    }

    public function testRejectsExpired(): void
    {
        $config = new Config(['app' => ['key' => 'test-secret-key']], sys_get_temp_dir());
        $signer = new SignedUrl($config);
        $url = $signer->sign('/preview/abc', [], 60);
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);
        /** @var array<string, string> $params */
        $params['expires'] = (string) (time() - 10);
        unset($params['sig']);
        ksort($params);
        $params['sig'] = hash_hmac(
            'sha256',
            '/preview/abc?' . http_build_query($params),
            'test-secret-key',
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('preview.expired');
        $signer->assertValid('/preview/abc', $params);
    }

    public function testRejectsMissingSignature(): void
    {
        $config = new Config(['app' => ['key' => 'test-secret-key']], sys_get_temp_dir());
        $signer = new SignedUrl($config);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('preview.invalid');
        $signer->assertValid('/preview/abc', ['expires' => (string) (time() + 60)]);
    }
}
