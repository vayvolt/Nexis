<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;

/**
 * Guards against locking yourself out of admin and against removing the last admin.
 */
final class AdminMembershipGuard
{
    public const ERR_SELF_ADMIN_RIGHTS = 'users_self_admin_rights';
    public const ERR_LAST_SITE_ADMIN = 'users_last_site_admin';
    public const ERR_LAST_PLATFORM_ADMIN = 'users_last_platform_admin';

    public function __construct(
        private UserRepository $users,
    ) {
    }

    /**
     * @return ?string Error key suffix for admin.error.* or null when allowed
     */
    public function assertUpdateAllowed(
        User $actor,
        User $target,
        SiteId $siteId,
        string $newRoleId,
        bool $newPlatformAdmin,
    ): ?string {
        $currentRoleSlug = $this->users->roleSlugFor($target->id, $siteId);
        $newRoleSlug = $this->roleSlugForId($siteId, $newRoleId);

        if ($actor->id->equals($target->id)) {
            if ($currentRoleSlug === RoleSlug::ADMIN && $newRoleSlug !== RoleSlug::ADMIN) {
                return self::ERR_SELF_ADMIN_RIGHTS;
            }
            if ($target->isPlatformAdmin && !$newPlatformAdmin) {
                return self::ERR_SELF_ADMIN_RIGHTS;
            }
        }

        $wasSiteAdmin = $currentRoleSlug === RoleSlug::ADMIN;
        $willBeSiteAdmin = $newRoleSlug === RoleSlug::ADMIN;
        if ($wasSiteAdmin && !$willBeSiteAdmin && $this->users->countWithRoleSlug($siteId, RoleSlug::ADMIN) <= 1) {
            return self::ERR_LAST_SITE_ADMIN;
        }

        if ($target->isPlatformAdmin && !$newPlatformAdmin && $this->users->countPlatformAdmins() <= 1) {
            return self::ERR_LAST_PLATFORM_ADMIN;
        }

        return null;
    }

    /**
     * @return ?string Error key suffix for admin.error.* or null when allowed
     */
    public function assertDeleteAllowed(User $actor, User $target, SiteId $siteId): ?string
    {
        if ($actor->id->equals($target->id)) {
            return 'users_self_delete';
        }

        if ($this->users->roleSlugFor($target->id, $siteId) === RoleSlug::ADMIN
            && $this->users->countWithRoleSlug($siteId, RoleSlug::ADMIN) <= 1) {
            return self::ERR_LAST_SITE_ADMIN;
        }

        if ($target->isPlatformAdmin && $this->users->countPlatformAdmins() <= 1) {
            return self::ERR_LAST_PLATFORM_ADMIN;
        }

        return null;
    }

    private function roleSlugForId(SiteId $siteId, string $roleId): ?string
    {
        if ($roleId === '') {
            return null;
        }
        foreach ($this->users->listRoles($siteId) as $role) {
            if ($role['id'] === $roleId) {
                return $role['slug'];
            }
        }

        return null;
    }
}
