<?php

declare(strict_types=1);

namespace Nexis\Content;

use DateTimeImmutable;

/**
 * Public byline for dated content (blog posts, etc.).
 */
final class PublishMetaHtml
{
    public static function formatDate(DateTimeImmutable $date, string $locale): string
    {
        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
        );
        $formatted = $formatter->format($date);
        if (is_string($formatted) && $formatted !== '') {
            return $formatted;
        }

        return $date->format(str_starts_with(strtolower($locale), 'en') ? 'M j, Y' : 'd.m.Y');
    }

    /**
     * @param callable(string): string|null $authorLabel Maps author display name → localized label (e.g. "Von Max")
     */
    public static function renderHtml(Page $page, string $locale, ?callable $authorLabel = null): string
    {
        $date = $page->displayDate;
        $author = $page->authorName;
        if ($date === null && ($author === null || $author === '')) {
            return '';
        }

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $parts = [];
        if ($date !== null) {
            $parts[] = '<time datetime="' . $e($date->format('c')) . '">'
                . $e(self::formatDate($date, $locale)) . '</time>';
        }
        if ($author !== null && $author !== '') {
            $label = $authorLabel !== null ? (string) $authorLabel($author) : $author;
            $parts[] = '<span class="nx-blog-meta__author">' . $e($label) . '</span>';
        }

        return '<p class="nx-blog-meta">' . implode('<span class="nx-blog-meta__sep" aria-hidden="true"> · </span>', $parts) . '</p>';
    }
}
