<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Auth\UserId;
use Nexis\Site\SiteId;

final readonly class PluginToggled
{
    public function __construct(
        public SiteId $siteId,
        public string $pluginKey,
        public bool $enabled,
        public UserId $actorId,
    ) {
    }

    public function eventName(): string
    {
        return $this->enabled ? 'plugin.enabled' : 'plugin.disabled';
    }
}
