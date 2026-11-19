<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Auth\UserId;
use Nexis\Site\SiteId;

final readonly class UserAuthenticated
{
    public function __construct(
        public UserId $userId,
        public ?SiteId $siteId,
        /** admin | account | session */
        public string $channel = 'session',
    ) {
    }
}
