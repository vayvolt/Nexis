<?php

declare(strict_types=1);

namespace Nexis\Http;

/**
 * Minimal HTTP client for outbound JSON/form calls (OIDC, etc.).
 */
interface SimpleHttpClient
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array;
}
