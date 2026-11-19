<?php

declare(strict_types=1);

namespace Nexis\Content;

/**
 * Lightweight on-page SEO checklist for the admin builder (not a ranking guarantee).
 *
 * @phpstan-type SeoCheck array{id: string, ok: bool, weight: int}
 */
final class PageSeoAnalysis
{
    /**
     * @return array{score: int, checks: list<SeoCheck>}
     */
    public static function analyze(Page $page): array
    {
        $keyword = mb_strtolower(trim((string) ($page->focusKeyword ?? '')));
        $seoTitle = trim($page->documentTitle);
        $seoDesc = trim($page->documentDescription);
        $titleLen = mb_strlen($seoTitle);
        $descLen = mb_strlen($seoDesc);
        $robotsIndex = !str_contains(strtolower($page->robots), 'noindex');

        $containsKeyword = static function (string $haystack) use ($keyword): bool {
            if ($keyword === '') {
                return false;
            }

            return mb_stripos($haystack, $keyword) !== false;
        };

        $checks = [
            ['id' => 'keyword_set', 'ok' => $keyword !== '', 'weight' => 15],
            ['id' => 'keyword_in_title', 'ok' => $keyword !== '' && $containsKeyword($seoTitle), 'weight' => 20],
            ['id' => 'keyword_in_description', 'ok' => $keyword !== '' && $containsKeyword($seoDesc), 'weight' => 15],
            ['id' => 'title_length', 'ok' => $titleLen >= 30 && $titleLen <= 60, 'weight' => 20],
            ['id' => 'description_length', 'ok' => $descLen >= 70 && $descLen <= 160, 'weight' => 20],
            ['id' => 'robots_index', 'ok' => $robotsIndex, 'weight' => 10],
        ];

        $earned = 0;
        $total = 0;
        foreach ($checks as $check) {
            $total += $check['weight'];
            if ($check['ok']) {
                $earned += $check['weight'];
            }
        }
        $score = (int) round(($earned / $total) * 100);

        return [
            'score' => $score,
            'checks' => $checks,
        ];
    }
}
