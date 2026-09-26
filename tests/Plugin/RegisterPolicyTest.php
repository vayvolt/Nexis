<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Auth\PolicyRegistry;
use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\TenantId;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class RegisterPolicyTest extends TestCase
{
    use PluginKernelTestFactory;

    public function testRegisterPolicyWiresIntoRegistry(): void
    {
        $policies = new PolicyRegistry();
        $kernel = $this->makePluginKernel(policies: $policies);
        $kernel->forPlugin('acme/shop');
        $kernel->registerPolicy('shop.order.view', static function (User $user, Site $site, mixed $subject): bool {
            return is_array($subject) && ($subject['user_id'] ?? '') === $user->id->value;
        });

        $user = new User(new UserId(Uuid::v7()), 'a@test', 'x', 'A', false, 'de');
        $site = new Site(
            new SiteId(Uuid::v7()),
            new TenantId(Uuid::v7()),
            'S',
            'localhost',
            'de',
            LocaleUrlStrategy::None,
            [],
        );

        self::assertTrue($policies->has('shop.order.view'));
        self::assertTrue($policies->allows($user, $site, 'shop.order.view', ['user_id' => $user->id->value]));
        self::assertFalse($policies->allows($user, $site, 'shop.order.view', ['user_id' => 'other']));
    }
}
