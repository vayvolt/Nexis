<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\BlockRegistry;
use Nexis\Builder\BlockRenderer;
use Nexis\Builder\BlockType;
use Nexis\Event\BlockRendering;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PageRendering;
use PHPUnit\Framework\TestCase;

final class BlockRendererEventsTest extends TestCase
{
    public function testPageAndBlockRenderingCanEnrichProps(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(PageRendering::class, static function (PageRendering $event): void {
            $event->context['flag'] = 'page';
        });
        $dispatcher->listen(BlockRendering::class, static function (BlockRendering $event): void {
            $props = is_array($event->block['props'] ?? null) ? $event->block['props'] : [];
            $props['badge'] = 'sale';
            $event->block['props'] = $props;
        });

        $block = new class implements BlockType {
            public function type(): string
            {
                return 'test/plain';
            }

            public function label(): string
            {
                return 'Plain';
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
                $badge = (string) (($block['props']['badge'] ?? ''));
                $flag = (string) ($context['flag'] ?? '');

                return 'badge=' . $badge . ';flag=' . $flag;
            }
        };

        $html = (new BlockRenderer(new BlockRegistry([$block]), null, $dispatcher))->renderDocument([
            'schemaVersion' => 1,
            'root' => [
                'type' => 'test/plain',
                'props' => [],
            ],
        ], []);

        self::assertSame('badge=sale;flag=page', $html);
    }

    public function testBlockRenderingListenerFailureIsFailSoft(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->listen(BlockRendering::class, static function (): void {
            throw new \RuntimeException('listener boom');
        });

        $block = new class implements BlockType {
            public function type(): string
            {
                return 'test/ok';
            }

            public function label(): string
            {
                return 'Ok';
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
                return 'ok';
            }
        };

        $html = (new BlockRenderer(new BlockRegistry([$block]), null, $dispatcher))->renderBlock([
            'type' => 'test/ok',
            'props' => [],
        ]);

        self::assertSame('ok', $html);
    }
}
