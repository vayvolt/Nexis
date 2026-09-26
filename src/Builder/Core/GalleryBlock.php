<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class GalleryBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/gallery';
    }

    public function label(): string
    {
        return 'Galerie';
    }

    public function defaultProps(): array
    {
        return [
            'assetIds' => '',
            'columns' => '3',
            'caption' => '',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'assetIds' => (object) ['type' => 'string'],
                'columns' => (object) ['type' => 'string', 'enum' => ['2', '3', '4']],
                'caption' => (object) ['type' => 'string'],
            ],
            'required' => ['columns'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $ids = self::parseAssetIds((string) ($props['assetIds'] ?? ''));
        $columns = (string) ($props['columns'] ?? '3');
        if (!in_array($columns, ['2', '3', '4'], true)) {
            $columns = '3';
        }
        $caption = trim((string) ($props['caption'] ?? ''));
        $basePath = (string) ($context['basePath'] ?? '');
        /** @var array<string, array{src?: string, srcWebp?: string, alt?: string, focusX?: string, focusY?: string}> $items */
        $items = is_array($props['items'] ?? null) ? $props['items'] : [];

        if ($ids === [] && $items === []) {
            return '<figure class="bk-gallery bk-gallery--empty"><p>Keine Bilder gewählt.</p></figure>';
        }

        $html = '<figure class="bk-gallery bk-gallery--cols-' . $this->e($columns) . '"><ul class="bk-gallery__grid">';
        if ($items !== []) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $html .= $this->renderItem(
                    (string) ($item['src'] ?? ''),
                    (string) ($item['srcWebp'] ?? ''),
                    (string) ($item['alt'] ?? ''),
                    (string) ($item['focusX'] ?? '50'),
                    (string) ($item['focusY'] ?? '50'),
                );
            }
        } else {
            foreach ($ids as $id) {
                $src = $basePath . '/media/' . $id;
                $html .= $this->renderItem($src, '', '');
            }
        }
        $html .= '</ul>';
        if ($caption !== '') {
            $html .= '<figcaption class="bk-gallery__caption">' . $this->e($caption) . '</figcaption>';
        }
        $html .= '</figure>';

        return $html;
    }

    private function renderItem(string $src, string $srcWebp, string $alt, string $focusX = '50', string $focusY = '50'): string
    {
        if ($src === '') {
            return '';
        }
        $style = ImageBlock::objectPositionStyle($focusX, $focusY);
        $img = '<img src="' . $this->e($src) . '" alt="' . $this->e($alt) . '" loading="lazy"'
            . ($style !== '' ? ' style="' . $this->e($style) . '"' : '') . '>';
        if ($srcWebp !== '') {
            $img = '<picture><source type="image/webp" srcset="' . $this->e($srcWebp) . '">' . $img . '</picture>';
        }

        return '<li class="bk-gallery__item">' . $img . '</li>';
    }

    /**
     * @return list<string>
     */
    public static function parseAssetIds(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $ids = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '' && !in_array($part, $ids, true)) {
                $ids[] = $part;
            }
        }

        return $ids;
    }
}
