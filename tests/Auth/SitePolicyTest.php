<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\MembershipLookup;
use Nexis\Auth\Permission;
use Nexis\Auth\PermissionLookup;
use Nexis\Auth\PolicyRegistry;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\TenantId;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class SitePolicyTest extends TestCase
{
    public function testMembershipIsRequiredUnlessPlatformAdmin(): void
    {
        $siteA = new SiteId(Uuid::v7());
        $siteB = new SiteId(Uuid::v7());
        $alice = new User(new UserId(Uuid::v7()), 'a@test', 'x', 'Alice', false, 'de');
        $admin = new User(new UserId(Uuid::v7()), 'admin@test', 'x', 'Admin', true, 'de');
        $lookup = new class ($siteA) implements MembershipLookup {
            public function __construct(private SiteId $allowed)
            {
            }

            public function hasAccess(UserId $userId, SiteId $siteId): bool
            {
                return $siteId->equals($this->allowed);
            }

            public function siteIdsFor(UserId $userId): array
            {
                return [$this->allowed];
            }
        };
        $permissions = new class implements PermissionLookup {
            public function userHas(UserId $userId, SiteId $siteId, string $permission): bool
            {
                return false;
            }

            public function forUserOnSite(UserId $userId, SiteId $siteId): array
            {
                return [];
            }
        };
        $policy = new SitePolicy($lookup, $permissions, new PolicyRegistry());
        $siteEntity = static fn (SiteId $id): Site => new Site(
            $id,
            new TenantId(Uuid::v7()),
            'S',
            'localhost',
            'de',
            LocaleUrlStrategy::None,
            [],
        );

        self::assertTrue($policy->view($alice, $siteEntity($siteA)));
        self::assertFalse($policy->view($alice, $siteEntity($siteB)));
        self::assertTrue($policy->view($admin, $siteEntity($siteB)));
    }

    public function testCanRequiresPermissionForMembers(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $editor = new User(new UserId(Uuid::v7()), 'e@test', 'x', 'Editor', false, 'de');
        $admin = new User(new UserId(Uuid::v7()), 'a@test', 'x', 'Admin', true, 'de');
        $memberships = new class ($siteId) implements MembershipLookup {
            public function __construct(private SiteId $allowed)
            {
            }

            public function hasAccess(UserId $userId, SiteId $siteId): bool
            {
                return $siteId->equals($this->allowed);
            }

            public function siteIdsFor(UserId $userId): array
            {
                return [$this->allowed];
            }
        };
        $permissions = new class implements PermissionLookup {
            public function userHas(UserId $userId, SiteId $siteId, string $permission): bool
            {
                return $permission === Permission::CONTENT_PAGE_PUBLISH;
            }

            public function forUserOnSite(UserId $userId, SiteId $siteId): array
            {
                return [Permission::CONTENT_PAGE_PUBLISH];
            }
        };
        $policy = new SitePolicy($memberships, $permissions, new PolicyRegistry());
        $site = new Site(
            $siteId,
            new TenantId(Uuid::v7()),
            'S',
            'localhost',
            'de',
            LocaleUrlStrategy::None,
            [],
        );

        self::assertTrue($policy->can($editor, $site, Permission::CONTENT_PAGE_PUBLISH));
        self::assertFalse($policy->can($editor, $site, Permission::PLUGIN_MANAGE));
        self::assertTrue($policy->can($admin, $site, Permission::PLUGIN_MANAGE));
    }

    public function testAllowsUsesRegisteredPolicy(): void
    {
        $siteId = new SiteId(Uuid::v7());
        $editor = new User(new UserId(Uuid::v7()), 'e@test', 'x', 'Editor', false, 'de');
        $memberships = new class ($siteId) implements MembershipLookup {
            public function __construct(private SiteId $allowed)
            {
            }

            public function hasAccess(UserId $userId, SiteId $siteId): bool
            {
                return $siteId->equals($this->allowed);
            }

            public function siteIdsFor(UserId $userId): array
            {
                return [$this->allowed];
            }
        };
        $permissions = new class implements PermissionLookup {
            public function userHas(UserId $userId, SiteId $siteId, string $permission): bool
            {
                return false;
            }

            public function forUserOnSite(UserId $userId, SiteId $siteId): array
            {
                return [];
            }
        };
        $policies = new PolicyRegistry();
        $policies->register('demo.thing.edit', static function (User $user, Site $site, mixed $subject): bool {
            return is_array($subject) && ($subject['owner'] ?? '') === $user->email;
        });
        $policy = new SitePolicy($memberships, $permissions, $policies);
        $site = new Site(
            $siteId,
            new TenantId(Uuid::v7()),
            'S',
            'localhost',
            'de',
            LocaleUrlStrategy::None,
            [],
        );

        self::assertTrue($policy->allows($editor, $site, 'demo.thing.edit', ['owner' => 'e@test']));
        self::assertFalse($policy->allows($editor, $site, 'demo.thing.edit', ['owner' => 'other@test']));
        self::assertFalse($policy->allows($editor, $site, 'demo.missing', null));
    }
}
