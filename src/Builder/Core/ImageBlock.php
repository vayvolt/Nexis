<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class ImageBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/image';
    }

    public function label(): string
    {
        return 'Bild';
    }

    public function defaultProps(): array
    {
        return [
            'assetId' => '',
            'alt' => '',
            'src' => '',
            'srcWebp' => '',
            'focusX' => '50',
            'focusY' => '50',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'assetId' => (object) ['type' => 'string'],
                'alt' => (object) ['type' => 'string'],
                'src' => (object) ['type' => 'string'],
                'srcWebp' => (object) ['type' => 'string'],
                'focusX' => (object) ['type' => 'string'],
                'focusY' => (object) ['type' => 'string'],
            ],
            'required' => ['alt'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $alt = (string) ($props['alt'] ?? '');
        $src = (string) ($props['src'] ?? '');
        $srcWebp = (string) ($props['srcWebp'] ?? '');
        $assetId = (string) ($props['assetId'] ?? '');
        $basePath = (string) ($context['basePath'] ?? '');
        if ($src === '' && $assetId !== '') {
            $src = $basePath . '/media/' . $assetId;
        }
        if ($src === '') {
            return '<figure class="bk-image bk-image--empty"><p>Bild noch nicht gewählt.</p></figure>';
        }

        $style = self::objectPositionStyle(
            (string) ($props['focusX'] ?? '50'),
            (string) ($props['focusY'] ?? '50'),
        );
        $img = '<img src="' . $this->e($src) . '" alt="' . $this->e($alt) . '" loading="lazy"'
            . ($style !== '' ? ' style="' . $this->e($style) . '"' : '') . '>';
        if ($srcWebp !== '') {
            $img = '<picture><source type="image/webp" srcset="' . $this->e($srcWebp) . '">' . $img . '</picture>';
        }

        return '<figure class="bk-image">' . $img . '</figure>';
    }

    public static function objectPositionStyle(string $focusX, string $focusY): string
    {
        $x = self::clampFocus(is_numeric($focusX) ? (float) $focusX : 50.0);
        $y = self::clampFocus(is_numeric($focusY) ? (float) $focusY : 50.0);
        if ($x === 50.0 && $y === 50.0) {
            return '';
        }

        return 'object-position: ' . $x . '% ' . $y . '%';
    }

    public static function clampFocus(float $value): float
    {
        if ($value < 0.0) {
            return 0.0;
        }
        if ($value > 100.0) {
            return 100.0;
        }

        return round($value, 2);
    }
}
