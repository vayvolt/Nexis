<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\AdminMembershipGuard;
use Nexis\Auth\RoleSlug;
use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Auth\UserRepository;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class AdminMembershipGuardTest extends TestCase
{
    public function testSelfCannotDemoteSiteAdminRole(): void
    {
        $adminId = new UserId(Uuid::v7());
        $adminRoleId = Uuid::v7();
        $editorRoleId = Uuid::v7();
        $siteId = new SiteId(Uuid::v7());
        $admin = $this->user($adminId, true);

        $users = $this->createMock(UserRepository::class);
        $users->method('roleSlugFor')->willReturn(RoleSlug::ADMIN);
        $users->method('listRoles')->willReturn([
            ['id' => $adminRoleId, 'name' => 'Website-Admin', 'slug' => RoleSlug::ADMIN],
            ['id' => $editorRoleId, 'name' => 'Redakteur', 'slug' => RoleSlug::EDITOR],
        ]);
        $users->method('countWithRoleSlug')->willReturn(2);
        $users->method('countPlatformAdmins')->willReturn(2);

        $guard = new AdminMembershipGuard($users);
        self::assertSame(
            AdminMembershipGuard::ERR_SELF_ADMIN_RIGHTS,
            $guard->assertUpdateAllowed($admin, $admin, $siteId, $editorRoleId, true),
        );
    }

    public function testSelfCannotRemovePlatformAdmin(): void
    {
        $adminId = new UserId(Uuid::v7());
        $adminRoleId = Uuid::v7();
        $siteId = new SiteId(Uuid::v7());
        $admin = $this->user($adminId, true);

        $users = $this->createMock(UserRepository::class);
        $users->method('roleSlugFor')->willReturn(RoleSlug::ADMIN);
        $users->method('listRoles')->willReturn([
            ['id' => $adminRoleId, 'name' => 'Website-Admin', 'slug' => RoleSlug::ADMIN],
        ]);
        $users->method('countWithRoleSlug')->willReturn(2);
        $users->method('countPlatformAdmins')->willReturn(2);

        $guard = new AdminMembershipGuard($users);
        self::assertSame(
            AdminMembershipGuard::ERR_SELF_ADMIN_RIGHTS,
            $guard->assertUpdateAllowed($admin, $admin, $siteId, $adminRoleId, false),
        );
    }

    public function testCannotDemoteLastSiteAdmin(): void
    {
        $actorId = new UserId(Uuid::v7());
        $targetId = new UserId(Uuid::v7());
        $adminRoleId = Uuid::v7();
        $editorRoleId = Uuid::v7();
        $siteId = new SiteId(Uuid::v7());
        $actor = $this->user($actorId, true);
        $target = $this->user($targetId, false);

        $users = $this->createMock(UserRepository::class);
        $users->method('roleSlugFor')->willReturn(RoleSlug::ADMIN);
        $users->method('listRoles')->willReturn([
            ['id' => $adminRoleId, 'name' => 'Website-Admin', 'slug' => RoleSlug::ADMIN],
            ['id' => $editorRoleId, 'name' => 'Redakteur', 'slug' => RoleSlug::EDITOR],
        ]);
        $users->method('countWithRoleSlug')->willReturn(1);
        $users->method('countPlatformAdmins')->willReturn(1);

        $guard = new AdminMembershipGuard($users);
        self::assertSame(
            AdminMembershipGuard::ERR_LAST_SITE_ADMIN,
            $guard->assertUpdateAllowed($actor, $target, $siteId, $editorRoleId, false),
        );
    }

    public function testCannotDeleteLastPlatformAdmin(): void
    {
        $actorId = new UserId(Uuid::v7());
        $targetId = new UserId(Uuid::v7());
        $siteId = new SiteId(Uuid::v7());
        $actor = $this->user($actorId, true);
        $target = $this->user($targetId, true);

        $users = $this->createMock(UserRepository::class);
        $users->method('roleSlugFor')->willReturn(RoleSlug::EDITOR);
        $users->method('countWithRoleSlug')->willReturn(2);
        $users->method('countPlatformAdmins')->willReturn(1);

        $guard = new AdminMembershipGuard($users);
        self::assertSame(
            AdminMembershipGuard::ERR_LAST_PLATFORM_ADMIN,
            $guard->assertDeleteAllowed($actor, $target, $siteId),
        );
    }

    public function testAllowsDemoteWhenAnotherSiteAdminExists(): void
    {
        $actorId = new UserId(Uuid::v7());
        $targetId = new UserId(Uuid::v7());
        $adminRoleId = Uuid::v7();
        $editorRoleId = Uuid::v7();
        $siteId = new SiteId(Uuid::v7());
        $actor = $this->user($actorId, true);
        $target = $this->user($targetId, false);

        $users = $this->createMock(UserRepository::class);
        $users->method('roleSlugFor')->willReturn(RoleSlug::ADMIN);
        $users->method('listRoles')->willReturn([
            ['id' => $adminRoleId, 'name' => 'Website-Admin', 'slug' => RoleSlug::ADMIN],
            ['id' => $editorRoleId, 'name' => 'Redakteur', 'slug' => RoleSlug::EDITOR],
        ]);
        $users->method('countWithRoleSlug')->willReturn(2);
        $users->method('countPlatformAdmins')->willReturn(1);

        $guard = new AdminMembershipGuard($users);
        self::assertNull($guard->assertUpdateAllowed($actor, $target, $siteId, $editorRoleId, false));
    }

    private function user(UserId $id, bool $platformAdmin): User
    {
        return new User($id, 'u@example.com', 'hash', 'User', $platformAdmin, 'de');
    }
}
