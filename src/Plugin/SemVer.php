<?php

declare(strict_types=1);

namespace Nexis\Plugin;

final class SemVer
{
    public static function satisfies(string $version, string $constraint): bool
    {
        $version = self::normalize(ltrim(trim($version), 'v'));
        $constraint = trim($constraint);
        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        if (str_starts_with($constraint, '^')) {
            $base = self::normalize(ltrim(substr($constraint, 1), 'v'));
            [$vMaj, $vMin] = self::parts($version);
            [$bMaj, $bMin] = self::parts($base);
            if ($vMaj !== $bMaj) {
                return false;
            }
            if ($vMaj === 0) {
                return $vMin === $bMin && self::compare($version, $base) >= 0;
            }

            return self::compare($version, $base) >= 0;
        }

        if (str_starts_with($constraint, '>=')) {
            $base = self::normalize(ltrim(substr($constraint, 2), ' v'));

            return self::compare($version, $base) >= 0;
        }

        return self::compare($version, self::normalize(ltrim($constraint, 'v'))) === 0;
    }

    public static function normalize(string $version): string
    {
        $clean = explode('-', $version)[0];
        $bits = array_map('intval', explode('.', $clean));

        return sprintf(
            '%d.%d.%d',
            $bits[0] ?? 0,
            $bits[1] ?? 0,
            $bits[2] ?? 0,
        );
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function parts(string $version): array
    {
        $normalized = self::normalize($version);
        [$maj, $min, $pat] = explode('.', $normalized);

        return [(int) $maj, (int) $min, (int) $pat];
    }

    public static function compare(string $a, string $b): int
    {
        return self::parts($a) <=> self::parts($b);
    }
}
