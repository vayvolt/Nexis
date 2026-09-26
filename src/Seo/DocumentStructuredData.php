<?php

declare(strict_types=1);

namespace Nexis\Seo;

/**
 * Walks a builder document for FAQ items and the first image (core SEO helpers).
 *
 * @phpstan-type FaqItem array{question: string, answer: string}
 */
final class DocumentStructuredData
{
    /**
     * @param array<string, mixed> $document
     * @return list<FaqItem>
     */
    public static function faqItems(array $document): array
    {
        $items = [];
        self::walk(self::root($document), static function (array $block) use (&$items): void {
            if (($block['type'] ?? '') !== 'nexis/faq/item') {
                return;
            }
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $question = trim((string) ($props['question'] ?? ''));
            if ($question === '') {
                return;
            }
            $items[] = [
                'question' => $question,
                'answer' => trim((string) ($props['answer'] ?? '')),
            ];
        });

        return $items;
    }

    /**
     * Absolute or site-relative URL of the first core/image with a src.
     *
     * @param array<string, mixed> $document
     */
    public static function firstImageSrc(array $document, string $basePath = ''): ?string
    {
        $src = null;
        self::walk(self::root($document), static function (array $block) use (&$src, $basePath): void {
            if ($src !== null || ($block['type'] ?? '') !== 'core/image') {
                return;
            }
            $props = is_array($block['props'] ?? null) ? $block['props'] : [];
            $candidate = trim((string) ($props['src'] ?? ''));
            if ($candidate === '') {
                $assetId = trim((string) ($props['assetId'] ?? ''));
                if ($assetId !== '') {
                    $candidate = rtrim($basePath, '/') . '/media/' . $assetId;
                }
            }
            if ($candidate !== '') {
                $src = $candidate;
            }
        });

        return $src;
    }

    /**
     * @param array<string, mixed> $document
     * @param callable(array<string, mixed>): void $visitor
     */
    public static function walkDocument(array $document, callable $visitor): void
    {
        self::walk(self::root($document), $visitor);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private static function root(array $document): array
    {
        $root = $document['root'] ?? null;

        return is_array($root) ? $root : $document;
    }

    /**
     * @param array<string, mixed> $node
     * @param callable(array<string, mixed>): void $visitor
     */
    private static function walk(array $node, callable $visitor): void
    {
        if (isset($node['type']) && is_string($node['type'])) {
            $visitor($node);
        }
        $children = $node['children'] ?? null;
        if (!is_array($children)) {
            $blocks = $node['blocks'] ?? null;
            if (is_array($blocks)) {
                foreach ($blocks as $child) {
                    if (is_array($child)) {
                        self::walk($child, $visitor);
                    }
                }
            }

            return;
        }
        foreach ($children as $child) {
            if (is_array($child)) {
                self::walk($child, $visitor);
            }
        }
    }
}
