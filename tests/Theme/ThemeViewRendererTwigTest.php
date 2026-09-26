<?php

declare(strict_types=1);

namespace Nexis\Tests\Theme;

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
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new \RuntimeException('missing');
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
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
}
