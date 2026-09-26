<?php

declare(strict_types=1);

namespace Nexis\Support;

final class Uuid
{
    public static function v7(): string
    {
        $bytes = random_bytes(16);
        $timestampMs = (int) floor(microtime(true) * 1000);

        $bytes[0] = chr(($timestampMs >> 40) & 0xFF);
        $bytes[1] = chr(($timestampMs >> 32) & 0xFF);
        $bytes[2] = chr(($timestampMs >> 24) & 0xFF);
        $bytes[3] = chr(($timestampMs >> 16) & 0xFF);
        $bytes[4] = chr(($timestampMs >> 8) & 0xFF);
        $bytes[5] = chr($timestampMs & 0xFF);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    public static function isValid(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }
}
