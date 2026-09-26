<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;

interface MembershipLookup
{
    public function hasAccess(UserId $userId, SiteId $siteId): bool;

    /**
     * @return list<SiteId>
     */
    public function siteIdsFor(UserId $userId): array;
}
