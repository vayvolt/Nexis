<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\BlockRegistry;
use Nexis\Builder\BlockRenderer;
use Nexis\Builder\BlockType;
use PHPUnit\Framework\TestCase;

final class BlockRendererFailSoftTest extends TestCase
{
    public function testUnknownBlockReturnsFallback(): void
    {
        $html = (new BlockRenderer(new BlockRegistry([])))->renderBlock([
            'type' => 'vendor/missing',
            'props' => [],
        ]);

        self::assertStringContainsString('bk-fallback', $html);
        self::assertStringContainsString('Block nicht verfügbar', $html);
    }

    public function testThrowingBlockReturnsFallbackInsteadOfBreaking(): void
    {
        $block = new class implements BlockType {
            public function type(): string
            {
                return 'test/boom';
            }

            public function label(): string
            {
                return 'Boom';
            }

            public function allowsChildren(): bool
            {
                return false;
            }

            public function propsSchema(): array
            {
                return [];
            }

            public function defaultProps(): array
            {
                return [];
            }

            public function render(array $block, array $context): string
            {
                throw new \RuntimeException('intentional');
            }
        };

        $html = (new BlockRenderer(new BlockRegistry([$block])))->renderBlock([
            'type' => 'test/boom',
            'props' => [],
        ]);

        self::assertStringContainsString('bk-fallback', $html);
        self::assertStringContainsString('Block konnte nicht gerendert werden', $html);
    }
}
