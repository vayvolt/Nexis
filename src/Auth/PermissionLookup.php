<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;

interface PermissionLookup
{
    public function userHas(UserId $userId, SiteId $siteId, string $permission): bool;

    /**
     * @return list<string>
     */
    public function forUserOnSite(UserId $userId, SiteId $siteId): array;
}
