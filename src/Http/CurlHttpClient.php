<?php

declare(strict_types=1);

namespace Nexis\Http;

use RuntimeException;

final class CurlHttpClient implements SimpleHttpClient
{
    public function __construct(
        private int $timeout = 20,
        private int $connectTimeout = 5,
    ) {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('ext-curl is required for HTTP requests.');
        }

        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new RuntimeException('HTTP method is required.');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (!is_string($responseBody)) {
            throw new RuntimeException('HTTP request failed' . ($error !== '' ? ': ' . $error : ''));
        }

        return [
            'status' => $status,
            'body' => $responseBody,
        ];
    }
}
