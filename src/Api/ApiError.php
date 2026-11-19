<?php

declare(strict_types=1);

namespace Nexis\Api;

use Nexis\Http\Middleware\RequestIdMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use stdClass;

/**
 * Uniform API error envelope (docs/07-api-und-schnittstellen.md §7.2).
 */
final class ApiError
{
    public const UNAUTHORIZED = 'auth.unauthorized';
    public const FORBIDDEN = 'auth.forbidden';
    public const NOT_FOUND = 'resource.not_found';
    public const INVALID_REQUEST = 'request.invalid';
    public const INVALID_JSON = 'http.invalid_json';
    public const SITE_UNAVAILABLE = 'site.unavailable';

    /**
     * @param array<string, mixed> $details
     * @return array{error: array{code: string, message: string, details: array<string, mixed>|stdClass}, requestId: string}
     */
    public static function payload(
        ServerRequestInterface $request,
        string $code,
        string $message,
        array $details = [],
    ): array {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details === [] ? new stdClass() : $details,
            ],
            'requestId' => (string) $request->getAttribute(RequestIdMiddleware::ATTRIBUTE, ''),
        ];
    }
}
