<?php

declare(strict_types=1);

namespace Nexis\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Body decoding for the write API. PSR-7 only fills getParsedBody() for form
 * posts, so `application/json` is read from the raw stream (`php://input`).
 */
final class JsonBody
{
    /**
     * @return array<string, mixed>|null Decoded object, or null for malformed JSON
     */
    public static function decode(ServerRequestInterface $request): ?array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed) && $parsed !== []) {
            return $parsed;
        }

        $raw = trim((string) $request->getBody());
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
