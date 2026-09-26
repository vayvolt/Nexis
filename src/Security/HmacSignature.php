<?php

declare(strict_types=1);

namespace Nexis\Security;

/**
 * HMAC-SHA256 signatures for outbound webhooks and inbound provider callbacks.
 * Header format: sha256=<hex> (same as X-Nexis-Signature).
 */
final class HmacSignature
{
    public static function sign(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, string $secret, string $headerValue): bool
    {
        $headerValue = trim($headerValue);
        if ($headerValue === '' || $secret === '') {
            return false;
        }

        return hash_equals(self::sign($payload, $secret), $headerValue);
    }

    /**
     * Read raw body from a PSR-7 request (rewinds stream when possible).
     */
    public static function bodyFromRequest(\Psr\Http\Message\ServerRequestInterface $request): string
    {
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return $body->getContents();
    }
}
