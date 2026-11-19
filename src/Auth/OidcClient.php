<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Http\SimpleHttpClient;
use RuntimeException;

/**
 * Generic OpenID Connect (authorization code + userinfo).
 */
final class OidcClient
{
    public const PROVIDER = 'oidc';

    public function __construct(
        private SimpleHttpClient $http,
    ) {
    }

    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, userinfo_endpoint: string}
     */
    public function discover(OidcProviderConfig $config): array
    {
        $issuer = rtrim($config->issuer, '/');
        if ($issuer === '') {
            throw new RuntimeException('auth.oidc_not_configured');
        }

        $json = $this->getJson($issuer . '/.well-known/openid-configuration');
        $authorize = trim((string) ($json['authorization_endpoint'] ?? ''));
        $token = trim((string) ($json['token_endpoint'] ?? ''));
        $userinfo = trim((string) ($json['userinfo_endpoint'] ?? ''));
        if ($authorize === '' || $token === '' || $userinfo === '') {
            throw new RuntimeException('auth.oidc_discovery');
        }

        return [
            'authorization_endpoint' => $authorize,
            'token_endpoint' => $token,
            'userinfo_endpoint' => $userinfo,
        ];
    }

    public function authorizationUrl(
        OidcProviderConfig $config,
        string $redirectUri,
        string $state,
        string $nonce,
    ): string {
        if (!$config->isComplete()) {
            throw new RuntimeException('auth.oidc_not_configured');
        }
        $meta = $this->discover($config);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $config->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
        ], '', '&', PHP_QUERY_RFC3986);

        return $meta['authorization_endpoint'] . (str_contains($meta['authorization_endpoint'], '?') ? '&' : '?') . $query;
    }

    /**
     * Exchange authorization code and load userinfo claims.
     *
     * @return array{sub: string, email: string, email_verified: bool, name: string}
     */
    public function authenticateWithCode(
        OidcProviderConfig $config,
        string $code,
        string $redirectUri,
    ): array {
        if (!$config->isComplete()) {
            throw new RuntimeException('auth.oidc_not_configured');
        }
        $meta = $this->discover($config);

        $tokenBody = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $config->clientId,
            'client_secret' => $config->clientSecret,
        ], '', '&', PHP_QUERY_RFC3986);

        $tokenJson = $this->postForm($meta['token_endpoint'], $tokenBody);
        $accessToken = trim((string) ($tokenJson['access_token'] ?? ''));
        if ($accessToken === '') {
            throw new RuntimeException('auth.oidc_token');
        }

        $userinfo = $this->getJson($meta['userinfo_endpoint'], [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);

        $sub = trim((string) ($userinfo['sub'] ?? ''));
        $email = mb_strtolower(trim((string) ($userinfo['email'] ?? '')));
        if ($sub === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('auth.oidc_claims');
        }

        $verified = $userinfo['email_verified'] ?? true;
        $emailVerified = $verified === true || $verified === 1 || $verified === '1' || $verified === 'true';
        if (!$emailVerified) {
            throw new RuntimeException('auth.oidc_email_unverified');
        }

        $name = trim((string) ($userinfo['name'] ?? ''));
        if ($name === '') {
            $given = trim((string) ($userinfo['given_name'] ?? ''));
            $family = trim((string) ($userinfo['family_name'] ?? ''));
            $name = trim($given . ' ' . $family);
        }
        if ($name === '') {
            $name = strtok($email, '@') ?: $email;
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => true,
            'name' => $name,
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function getJson(string $url, array $headers = []): array
    {
        $headers = $headers + ['Accept' => 'application/json'];
        $response = $this->http->request('GET', $url, $headers);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('auth.oidc_http');
        }

        return $this->decodeJson($response['body']);
    }

    /**
     * @return array<string, mixed>
     */
    private function postForm(string $url, string $body): array
    {
        $response = $this->http->request('POST', $url, [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ], $body);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('auth.oidc_token');
        }

        return $this->decodeJson($response['body']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('auth.oidc_http', 0, $e);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('auth.oidc_http');
        }

        return $decoded;
    }
}
