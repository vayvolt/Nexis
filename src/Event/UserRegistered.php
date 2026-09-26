<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Auth\UserId;
use Nexis\Site\SiteId;

final readonly class UserRegistered
{
    public function __construct(
        public SiteId $siteId,
        public UserId $userId,
        public string $email,
    ) {
    }
}
