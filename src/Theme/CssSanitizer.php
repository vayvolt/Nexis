<?php

declare(strict_types=1);

namespace Nexis\Theme;

/**
 * Hardens CSS for inline &lt;style&gt; injection (tokens + custom CSS).
 * Strips HTML breakouts and a small set of high-risk constructs.
 * Not a full CSS property allowlist — privilege theme.custom_css still required.
 */
final class CssSanitizer
{
    public function sanitizeStylesheet(string $css): string
    {
        $css = str_replace("\0", '', $css);
        $css = preg_replace('/<\/?style\b[^>]*>/i', '', $css) ?? '';
        $css = preg_replace('/<[^>]*>/', '', $css) ?? '';
        $css = preg_replace('/@import\b[^;{]*;?/i', '', $css) ?? '';
        $css = preg_replace('/expression\s*\(/i', '', $css) ?? '';
        $css = preg_replace('/javascript\s*:/i', '', $css) ?? '';
        $css = preg_replace('/-moz-binding\s*:/i', '', $css) ?? '';
        $css = preg_replace('/\bbehavior\s*:/i', '', $css) ?? '';
        $css = preg_replace('/-o-link\s*:/i', '', $css) ?? '';

        return trim($css);
    }

    public function sanitizeDeclarationValue(string $value): string
    {
        $value = str_replace(["\0", '<', '>', '{', '}'], '', $value);
        $value = str_replace(';', '', $value);
        $value = preg_replace('/expression\s*\(/i', '', $value) ?? '';
        $value = preg_replace('/javascript\s*:/i', '', $value) ?? '';
        $value = preg_replace('/-moz-binding\s*:/i', '', $value) ?? '';
        if (preg_match('/url\s*\(/i', $value) === 1) {
            if (preg_match('/url\s*\(\s*[\'"]?(?:https?:\/\/|\/|data:image\/)/i', $value) !== 1) {
                $value = preg_replace('/url\s*\(.*/is', 'url()', $value) ?? 'url()';
            }
        }

        return trim($value);
    }

    /**
     * @param array<string, string> $tokens
     * @return array<string, string>
     */
    public function sanitizeTokenMap(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            $cleanKey = preg_replace('/[^a-zA-Z0-9._\-]/', '', $key) ?? '';
            if ($cleanKey === '') {
                continue;
            }
            $cleanValue = $this->sanitizeDeclarationValue($value);
            if ($cleanValue === '') {
                continue;
            }
            $out[$cleanKey] = $cleanValue;
        }

        return $out;
    }
}
