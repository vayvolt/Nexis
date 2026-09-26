<?php

declare(strict_types=1);

namespace Nexis\Cache;

use Nexis\Support\Uuid;

/**
 * Placeholders for per-request values in HTML stored by the full-page cache.
 */
final class DynamicPageTokens
{
    public const CSRF = '__NEXIS_CSRF_TOKEN__';
    public const IDEMPOTENCY = '__NEXIS_IDEM_KEY__';

    public static function hydrate(string $html, string $csrf): string
    {
        if (!str_contains($html, self::CSRF) && !str_contains($html, self::IDEMPOTENCY)) {
            return $html;
        }

        $html = str_replace(self::CSRF, $csrf, $html);
        while (str_contains($html, self::IDEMPOTENCY)) {
            try {
                $key = Uuid::v7();
            } catch (\Throwable) {
                $key = bin2hex(random_bytes(16));
            }
            $pos = strpos($html, self::IDEMPOTENCY);
            if ($pos === false) {
                break;
            }
            $html = substr_replace($html, $key, $pos, strlen(self::IDEMPOTENCY));
        }

        return $html;
    }

    public static function isDynamic(string $html): bool
    {
        return str_contains($html, self::CSRF) || str_contains($html, self::IDEMPOTENCY);
    }
}
