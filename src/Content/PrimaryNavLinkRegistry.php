<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\Event\PluginToggled;
use Nexis\Site\SiteRepository;

/**
 * In-memory primary-nav link definitions registered by plugins during boot.
 */
final class PrimaryNavLinkRegistry
{
    /**
     * @var list<array{plugin: string, path: string, label: callable(string): string}>
     */
    private array $links = [];

    public function __construct(
        private PrimaryNavLinkSync $sync,
        private SiteRepository $sites,
    ) {
    }

    /**
     * @param callable(string $locale): string $labelForLocale
     */
    public function register(string $pluginKey, string $pagePath, callable $labelForLocale): void
    {
        $pagePath = '/' . ltrim(trim($pagePath), '/');
        if ($pagePath === '/') {
            return;
        }
        $this->links[] = [
            'plugin' => $pluginKey,
            'path' => $pagePath,
            'label' => $labelForLocale,
        ];
        $site = $this->sites->installed();
        if ($site !== null) {
            $this->sync->ensure($site->id, $pagePath, $labelForLocale);
        }
    }

    public function onPluginToggled(PluginToggled $event): void
    {
        foreach ($this->links as $link) {
            if ($link['plugin'] !== $event->pluginKey) {
                continue;
            }
            if ($event->enabled) {
                $this->sync->ensure($event->siteId, $link['path'], $link['label']);
            } else {
                $this->sync->remove($event->siteId, $link['path']);
            }
        }
    }
}
