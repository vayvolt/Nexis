<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Site\SiteId;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shared Idempotency-Key protocol (docs/07 §7.8) for form/payment endpoints.
 */
final class Idempotency
{
    public function __construct(
        private IdempotencyStore $store,
    ) {
    }

    public function keyFrom(ServerRequestInterface $request): string
    {
        $key = trim($request->getHeaderLine('Idempotency-Key'));
        if ($key !== '') {
            return $key;
        }

        return trim(RequestInput::string($request, '_idempotency_key'));
    }

    /**
     * @return array{kind: 'miss'}|array{kind: 'replay', status_code: int, response: array<string, mixed>}|array{kind: 'conflict'}
     */
    public function check(SiteId $siteId, string $key, string $requestHash): array
    {
        $key = trim($key);
        if ($key === '') {
            return ['kind' => 'miss'];
        }

        $existing = $this->store->find($siteId, $key);
        if ($existing === null) {
            return ['kind' => 'miss'];
        }
        if (!hash_equals($existing['request_hash'], $requestHash)) {
            return ['kind' => 'conflict'];
        }

        return [
            'kind' => 'replay',
            'status_code' => $existing['status_code'],
            'response' => $existing['response'],
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function remember(
        SiteId $siteId,
        string $key,
        string $requestHash,
        int $statusCode,
        array $response,
    ): void {
        $key = trim($key);
        if ($key === '') {
            return;
        }
        $this->store->remember($siteId, $key, $requestHash, $statusCode, $response);
    }

    public static function hash(string ...$parts): string
    {
        return IdempotencyStore::hashRequest(...$parts);
    }
}
