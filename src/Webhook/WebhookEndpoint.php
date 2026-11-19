<?php

declare(strict_types=1);

namespace Nexis\Webhook;

use Nexis\Site\SiteId;

final readonly class WebhookEndpoint
{
    /**
     * @param list<string> $events
     */
    public function __construct(
        public string $id,
        public SiteId $siteId,
        public string $url,
        public string $secret,
        public array $events,
        public bool $isActive,
    ) {
    }
}
