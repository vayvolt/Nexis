<?php

declare(strict_types=1);

namespace Nexis\Http;

use Psr\Http\Message\ServerRequestInterface;

final class RequestInput
{
    public static function string(ServerRequestInterface $request, string $key, string $default = ''): string
    {
        $body = $request->getParsedBody();
        if (!is_array($body) || !isset($body[$key]) || !is_string($body[$key])) {
            return $default;
        }

        return trim($body[$key]);
    }

    public static function query(ServerRequestInterface $request, string $key, string $default = ''): string
    {
        $value = $request->getQueryParams()[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
