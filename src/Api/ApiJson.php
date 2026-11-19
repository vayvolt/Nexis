<?php

declare(strict_types=1);

namespace Nexis\Api;

use Nexis\Http\Middleware\RequestIdMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Success envelope: `{ data, meta?, requestId }`. Mirrors ApiError so every
 * API response carries the request id of RequestIdMiddleware.
 */
final class ApiJson
{
    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     * @return array{data: array<string, mixed>|list<mixed>, meta?: array<string, mixed>|stdClass, requestId: string}
     */
    public static function payload(ServerRequestInterface $request, array $data, array $meta = []): array
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        $payload['requestId'] = (string) $request->getAttribute(RequestIdMiddleware::ATTRIBUTE, '');

        return $payload;
    }
}
