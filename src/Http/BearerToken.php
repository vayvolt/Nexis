<?php

declare(strict_types=1);

namespace Nexis\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads `Authorization: Bearer <secret>`. Token-authenticated requests carry no
 * browser session, so they are exempt from CSRF.
 */
final class BearerToken
{
    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        $header = trim($request->getHeaderLine('Authorization'));
        if ($header === '') {
            return null;
        }
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public static function present(ServerRequestInterface $request): bool
    {
        return self::fromRequest($request) !== null;
    }
}
