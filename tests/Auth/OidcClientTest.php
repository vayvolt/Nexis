<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\OidcClient;
use Nexis\Auth\OidcProviderConfig;
use Nexis\Http\SimpleHttpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OidcClientTest extends TestCase
{
    public function testAuthenticateWithCodeUsesUserinfo(): void
    {
        $config = new OidcProviderConfig('https://idp.example', 'client', 'secret');
        $http = new class implements SimpleHttpClient {
            public function request(string $method, string $url, array $headers = [], ?string $body = null): array
            {
                if (str_contains($url, '.well-known')) {
                    return [
                        'status' => 200,
                        'body' => json_encode([
                            'authorization_endpoint' => 'https://idp.example/auth',
                            'token_endpoint' => 'https://idp.example/token',
                            'userinfo_endpoint' => 'https://idp.example/userinfo',
                        ], JSON_THROW_ON_ERROR),
                    ];
                }
                if (str_contains($url, '/token')) {
                    return [
                        'status' => 200,
                        'body' => json_encode(['access_token' => 'atok'], JSON_THROW_ON_ERROR),
                    ];
                }
                if (str_contains($url, '/userinfo')) {
                    if (($headers['Authorization'] ?? null) !== 'Bearer atok') {
                        throw new RuntimeException('missing bearer');
                    }

                    return [
                        'status' => 200,
                        'body' => json_encode([
                            'sub' => 'abc',
                            'email' => 'User@Example.TEST',
                            'email_verified' => true,
                            'name' => 'Ada',
                        ], JSON_THROW_ON_ERROR),
                    ];
                }

                throw new RuntimeException('unexpected url ' . $url);
            }
        };

        $claims = (new OidcClient($http))->authenticateWithCode(
            $config,
            'code',
            'https://app.test/admin/login/oidc/callback',
        );

        self::assertSame('abc', $claims['sub']);
        self::assertSame('user@example.test', $claims['email']);
        self::assertSame('Ada', $claims['name']);
    }

    public function testRejectsUnverifiedEmail(): void
    {
        $config = new OidcProviderConfig('https://idp.example', 'client', 'secret');
        $http = new class implements SimpleHttpClient {
            public function request(string $method, string $url, array $headers = [], ?string $body = null): array
            {
                if (str_contains($url, '.well-known')) {
                    return [
                        'status' => 200,
                        'body' => json_encode([
                            'authorization_endpoint' => 'https://idp.example/auth',
                            'token_endpoint' => 'https://idp.example/token',
                            'userinfo_endpoint' => 'https://idp.example/userinfo',
                        ], JSON_THROW_ON_ERROR),
                    ];
                }
                if (str_contains($url, '/token')) {
                    return ['status' => 200, 'body' => '{"access_token":"atok"}'];
                }

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'sub' => 'abc',
                        'email' => 'user@example.test',
                        'email_verified' => false,
                    ], JSON_THROW_ON_ERROR),
                ];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('auth.oidc_email_unverified');
        (new OidcClient($http))->authenticateWithCode($config, 'code', 'https://app.test/cb');
    }
}
