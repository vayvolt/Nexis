<?php

declare(strict_types=1);

namespace Nexis\Tests\Theme;

use Nexis\Theme\CssSanitizer;
use Nexis\Theme\ThemeDiscovery;
use Nexis\Theme\ThemeManifestLoader;
use Nexis\Theme\TokenResolver;
use PHPUnit\Framework\TestCase;

final class TokenResolverTest extends TestCase
{
    public function testChildThemeMergesTokens(): void
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'themes';
        $discovery = new ThemeDiscovery($root, new ThemeManifestLoader());
        $child = $discovery->find('nexis/atelier-warm');
        self::assertNotNull($child);
        $tokens = (new TokenResolver($discovery, new CssSanitizer()))->resolve($child, [
            'color.brand.primary' => '#111111',
        ]);
        self::assertSame('#111111', $tokens['color.brand.primary']);
        self::assertSame('#faf6f1', $tokens['color.surface']);
        self::assertArrayHasKey('font.sans', $tokens);
    }

    public function testCssVariableNames(): void
    {
        $resolver = new TokenResolver(
            new ThemeDiscovery(sys_get_temp_dir(), new ThemeManifestLoader()),
            new CssSanitizer(),
        );
        $css = $resolver->toCssVariables(['color.brand.primary' => '#abc']);
        self::assertStringContainsString('--color-brand-primary: #abc;', $css);
    }
}
