<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\Site;
use Nexis\Site\SiteId;

final class SitePolicy
{
    public function __construct(
        private MembershipLookup $memberships,
        private PermissionLookup $permissions,
        private PolicyRegistry $policies,
    ) {
    }

    public function view(User $user, Site $site): bool
    {
        return $this->viewId($user, $site->id);
    }

    public function viewId(User $user, SiteId $siteId): bool
    {
        if ($user->isPlatformAdmin) {
            return true;
        }

        return $this->memberships->hasAccess($user->id, $siteId);
    }

    public function can(User $user, Site $site, string $permission): bool
    {
        return $this->canId($user, $site->id, $permission);
    }

    public function canId(User $user, SiteId $siteId, string $permission): bool
    {
        if ($user->isPlatformAdmin) {
            return true;
        }
        if (!$this->memberships->hasAccess($user->id, $siteId)) {
            return false;
        }

        return $this->permissions->userHas($user->id, $siteId, $permission);
    }

    /**
     * Object-level policy (plugin-registered). Platform admins always pass.
     */
    public function allows(User $user, Site $site, string $ability, mixed $subject = null): bool
    {
        if ($user->isPlatformAdmin) {
            return true;
        }
        if (!$this->memberships->hasAccess($user->id, $site->id)) {
            return false;
        }

        return $this->policies->allows($user, $site, $ability, $subject);
    }

    /**
     * CMS admin access requires at least one permission (members have membership but none).
     */
    public function canAccessCms(User $user, Site $site): bool
    {
        if ($user->isPlatformAdmin) {
            return true;
        }
        if (!$this->memberships->hasAccess($user->id, $site->id)) {
            return false;
        }

        return $this->permissions->forUserOnSite($user->id, $site->id) !== [];
    }
}
