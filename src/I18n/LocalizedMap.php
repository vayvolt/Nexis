<?php

declare(strict_types=1);

namespace Nexis\I18n;

/**
 * Locale → string maps stored as JSON on a single entity row (consent-style i18n).
 */
final class LocalizedMap
{
    /**
     * @param array<string, mixed> $map
     */
    public static function encode(array $map): string
    {
        $clean = [];
        foreach ($map as $locale => $value) {
            if (!is_string($locale) || $locale === '') {
                continue;
            }
            $clean[$locale] = is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    public static function decode(mixed $raw): array
    {
        if (is_array($raw)) {
            $out = [];
            foreach ($raw as $locale => $value) {
                if (is_string($locale) && is_string($value)) {
                    $out[$locale] = $value;
                }
            }

            return $out;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $locale => $value) {
            if (is_string($locale) && is_string($value)) {
                $out[$locale] = $value;
            }
        }

        return $out;
    }

    /**
     * @param array<string, string> $map
     */
    public static function get(array $map, string $locale, ?string $fallbackLocale = null): string
    {
        $locale = trim($locale);
        if ($locale !== '' && isset($map[$locale]) && $map[$locale] !== '') {
            return $map[$locale];
        }
        if ($fallbackLocale !== null) {
            $fallbackLocale = trim($fallbackLocale);
            if ($fallbackLocale !== '' && isset($map[$fallbackLocale]) && $map[$fallbackLocale] !== '') {
                return $map[$fallbackLocale];
            }
        }
        foreach ($map as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $posted field[locale] => value
     * @param list<string> $locales
     * @return array<string, string>
     */
    public static function fromPosted(array $posted, array $locales): array
    {
        $out = [];
        foreach ($locales as $locale) {
            if (!is_string($locale) || $locale === '') {
                continue;
            }
            $value = $posted[$locale] ?? '';
            $out[$locale] = is_string($value) ? trim($value) : '';
        }

        return $out;
    }
}
