<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Event\BlockRendering;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PageRendering;
use Psr\Log\LoggerInterface;
use Throwable;

final class BlockRenderer
{
    public function __construct(
        private BlockRegistry $registry,
        private ?LoggerInterface $logger = null,
        private ?EventDispatcher $events = null,
    ) {
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $context
     */
    public function renderDocument(array $document, array $context = []): string
    {
        if ($this->events !== null) {
            $event = new PageRendering($document, $context);
            try {
                $this->events->dispatch($event);
            } catch (Throwable $e) {
                $this->logger?->error('PageRendering listener failed', [
                    'exception' => $e->getMessage(),
                ]);
            }
            $document = $event->document;
            $context = $event->context;
        }

        $doc = BlockDocument::fromArray($document);
        $context['renderer'] = function (array $block, array $ctx): string {
            return $this->renderBlock($block, $ctx);
        };

        return $this->renderBlock($doc->root, $context);
    }

    /**
     * @param array<string, mixed> $block
     * @param array<string, mixed> $context
     */
    public function renderBlock(array $block, array $context = []): string
    {
        if ($this->events !== null) {
            $event = new BlockRendering($block, $context);
            try {
                $this->events->dispatch($event);
            } catch (Throwable $e) {
                $this->logger?->error('BlockRendering listener failed', [
                    'type' => (string) ($block['type'] ?? ''),
                    'exception' => $e->getMessage(),
                ]);
            }
            $block = $event->block;
            $context = $event->context;
        }

        $type = (string) ($block['type'] ?? '');
        $handler = $this->registry->get($type);
        if ($handler === null) {
            return $this->fallback($type, 'Block nicht verfügbar.');
        }

        if (!isset($context['renderer'])) {
            $context['renderer'] = function (array $child, array $ctx): string {
                return $this->renderBlock($child, $ctx);
            };
        }

        try {
            return $handler->render($block, $context);
        } catch (Throwable $e) {
            $this->logger?->error('Block render failed', [
                'type' => $type,
                'exception' => $e->getMessage(),
            ]);

            return $this->fallback($type, 'Block konnte nicht gerendert werden.');
        }
    }

    private function fallback(string $type, string $message): string
    {
        $label = htmlspecialchars($type !== '' ? $type : 'unbekannt', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div class="bk-fallback" data-block-type="' . $label . '">' . $text . '</div>';
    }
}
