<?php

declare(strict_types=1);

namespace Nexis\Tests\Theme;

use Nexis\Kernel\Nexis;
use Nexis\Theme\ThemeViewRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ThemeViewRendererTwigTest extends TestCase
{
    public function testRendersTwigTemplate(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-twig-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . DIRECTORY_SEPARATOR . 'hello.twig';
        file_put_contents($file, '<p>{{ name }}</p><div>{{ html|raw }}</div>');
        $container = $this->emptyContainer();
        try {
            $html = (new ThemeViewRenderer($container))->renderFile($file, [
                'name' => '<script>',
                'html' => '<strong>ok</strong>',
            ]);
            self::assertStringContainsString('&lt;script&gt;', $html);
            self::assertStringContainsString('<strong>ok</strong>', $html);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }

    public function testThemeHeadIncludesNexisFavicon(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-twig-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $file = $dir . DIRECTORY_SEPARATOR . 'head.twig';
        file_put_contents($file, '<head>{{ slot(\'theme.head\') }}</head>');
        try {
            $html = (new ThemeViewRenderer($this->emptyContainer()))->renderFile($file, [
                'basePath' => '/nexis',
            ]);
            $icon = Nexis::brandUrl('/nexis', Nexis::BRAND_ICON);
            self::assertStringContainsString('rel="icon"', $html);
            self::assertStringContainsString('type="image/svg+xml"', $html);
            self::assertStringContainsString('href="' . $icon . '"', $html);
        } finally {
            @unlink($file);
            @rmdir($dir);
        }
    }

    private function emptyContainer(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException('missing');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }
}
