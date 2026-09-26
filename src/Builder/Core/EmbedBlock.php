<?php

declare(strict_types=1);

namespace Nexis\Builder\Core;

final class EmbedBlock extends AbstractCoreBlock
{
    public function type(): string
    {
        return 'core/embed';
    }

    public function label(): string
    {
        return 'Video / Embed';
    }

    public function defaultProps(): array
    {
        return [
            'url' => '',
            'title' => '',
            'aspect' => '16:9',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'url' => (object) ['type' => 'string'],
                'title' => (object) ['type' => 'string'],
                'aspect' => (object) ['type' => 'string', 'enum' => ['16:9', '4:3', '1:1']],
            ],
            'required' => ['url'],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $url = trim((string) ($props['url'] ?? ''));
        $title = trim((string) ($props['title'] ?? ''));
        $aspect = (string) ($props['aspect'] ?? '16:9');
        if (!in_array($aspect, ['16:9', '4:3', '1:1'], true)) {
            $aspect = '16:9';
        }

        $embed = self::toEmbedSrc($url);
        if ($embed === null) {
            return '<div class="bk-embed bk-embed--empty"><p>Ungültige oder nicht erlaubte Video-URL (YouTube/Vimeo).</p></div>';
        }

        $ratioClass = 'bk-embed--' . str_replace(':', '-', $aspect);
        $titleAttr = $title !== '' ? $title : 'Eingebettetes Video';

        return '<div class="bk-embed ' . $this->e($ratioClass) . '">'
            . '<iframe src="' . $this->e($embed) . '" title="' . $this->e($titleAttr) . '" '
            . 'loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
            . 'allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>'
            . '</div>';
    }

    public static function toEmbedSrc(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with($url, 'https://')) {
            return null;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '');
        $query = [];
        if (isset($parts['query'])) {
            parse_str((string) $parts['query'], $query);
        }

        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
            if (preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) !== 1) {
                return null;
            }

            return 'https://www.youtube-nocookie.com/embed/' . $id;
        }
        if (in_array($host, ['www.youtube.com', 'youtube.com', 'm.youtube.com'], true)) {
            $id = '';
            if (str_starts_with($path, '/embed/')) {
                $id = substr($path, 7);
            } elseif (str_starts_with($path, '/shorts/')) {
                $id = substr($path, 8);
            } elseif (isset($query['v']) && is_string($query['v'])) {
                $id = $query['v'];
            }
            $id = explode('/', $id)[0];
            if (preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) !== 1) {
                return null;
            }

            return 'https://www.youtube-nocookie.com/embed/' . $id;
        }
        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)) {
            if (preg_match('#/(?:video/)?(\d+)#', $path, $m) !== 1) {
                return null;
            }

            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }
}
