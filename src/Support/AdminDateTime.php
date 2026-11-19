<?php

declare(strict_types=1);

namespace Nexis\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Nexis\I18n\Translator;

/**
 * Admin UI timestamps: store/parse as UTC (or parseable ISO), show Europe/Berlin
 * without milliseconds, pattern by UI locale.
 */
final class AdminDateTime
{
    public const DISPLAY_TZ = 'Europe/Berlin';

    public static function format(DateTimeInterface|string|null $value, string $uiLocale): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $dt = DateTimeImmutable::createFromInterface($value);
        } else {
            $raw = trim($value);
            if ($raw === '') {
                return '';
            }
            // Drop fractional seconds before parse when present as ".123" / ".123456".
            $raw = preg_replace('/\.\d+(?=(?:[+-]\d{2}:?\d{2}|Z)?$)/', '', $raw) ?? $raw;
            try {
                $dt = new DateTimeImmutable($raw);
            } catch (Exception) {
                return preg_replace('/\.\d+$/', '', $raw) ?? $raw;
            }
        }

        $locale = Translator::normalizeUiLocale($uiLocale);
        $pattern = match ($locale) {
            'en' => 'Y-m-d H:i:s',
            default => 'd.m.Y H:i:s',
        };

        return $dt->setTimezone(new DateTimeZone(self::DISPLAY_TZ))->format($pattern);
    }
}
