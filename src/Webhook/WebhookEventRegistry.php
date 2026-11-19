<?php

declare(strict_types=1);

namespace Nexis\Webhook;

/**
 * Core + plugin-registered webhook event names for Admin UI and validation.
 */
final class WebhookEventRegistry
{
    /** @var list<string> */
    private array $extra = [];

    public function register(string $eventName): void
    {
        $eventName = trim($eventName);
        if ($eventName === '' || in_array($eventName, $this->extra, true)) {
            return;
        }
        $this->extra[] = $eventName;
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_values(array_unique(array_merge(
            ['page.published', 'page.unpublished', 'page.review_submitted', 'page.review_rejected', 'plugin.enabled', 'plugin.disabled'],
            $this->extra,
        )));
    }
}
