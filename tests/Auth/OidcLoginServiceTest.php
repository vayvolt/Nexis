<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\AuthThrottle;
use Nexis\Auth\LoginService;
use Nexis\Auth\OidcClient;
use Nexis\Auth\OidcIdentity;
use Nexis\Auth\OidcIdentityRepository;
use Nexis\Auth\OidcLoginPolicy;
use Nexis\Auth\OidcLoginService;
use Nexis\Auth\PasswordHasher;
use Nexis\Auth\PdoWebAuthnCredentialRepository;
use Nexis\Auth\RoleSlug;
use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Auth\UserRepository;
use Nexis\Auth\WebAuthnService;
use Nexis\Event\EventDispatcher;
use Nexis\Http\SessionStore;
use Nexis\Kernel\Config;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;
use Nexis\Site\TenantId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OidcLoginServiceTest extends TestCase
{
    public function testLinksExistingUserByEmailAndCompletesLogin(): void
    {
        $site = $this->site();
        $userId = new UserId(Uuid::v7());
        $user = new User($userId, 'editor@example.test', 'hash', 'Editor', false, 'de');

        $users = $this->createMock(UserRepository::class);
        $users->method('findByEmail')->with('editor@example.test')->willReturn($user);
        $users->expects(self::once())->method('save')->with($user);

        $identities = $this->createMock(OidcIdentityRepository::class);
        $identities->method('findByProviderSubject')->willReturn(null);
        $identities->expects(self::once())->method('link')->with(
            $userId,
            OidcClient::PROVIDER,
            'sub-1',
            'editor@example.test',
        )->willReturn(new OidcIdentity(Uuid::v7(), $userId, OidcClient::PROVIDER, 'sub-1', 'editor@example.test'));

        $session = $this->arraySession();
        $service = new OidcLoginService($identities, $users, new PasswordHasher(), $this->loginService($users, $session));

        $result = $service->loginWithClaims($site, new OidcLoginPolicy(true, false), [
            'sub' => 'sub-1',
            'email' => 'editor@example.test',
            'email_verified' => true,
            'name' => 'Editor',
        ]);

        self::assertSame($userId->value, $result->id->value);
        self::assertSame($userId->value, $session->get('user_id'));
    }

    public function testRejectsUnknownEmailWhenAutoProvisionOff(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findByEmail')->willReturn(null);

        $identities = $this->createMock(OidcIdentityRepository::class);
        $identities->method('findByProviderSubject')->willReturn(null);

        $service = new OidcLoginService(
            $identities,
            $users,
            new PasswordHasher(),
            $this->loginService($users, $this->arraySession()),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('auth.oidc_no_account');
        $service->loginWithClaims($this->site(), new OidcLoginPolicy(true, false), [
            'sub' => 'sub-2',
            'email' => 'new@example.test',
            'email_verified' => true,
            'name' => 'New',
        ]);
    }

    public function testAutoProvisionsMemberWhenEnabled(): void
    {
        $site = $this->site();
        $createdId = new UserId(Uuid::v7());
        $created = new User($createdId, 'new@example.test', 'hash', 'New', false, 'de');

        $users = $this->createMock(UserRepository::class);
        $users->method('findByEmail')->willReturn(null);
        $users->method('findRoleIdBySlug')->with($site->id, RoleSlug::MEMBER)->willReturn('role-member');
        $users->expects(self::once())->method('createUser')->willReturn($created);
        $users->expects(self::once())->method('setMembership');
        $users->expects(self::once())->method('markEmailVerified')->with($createdId);
        $users->expects(self::once())->method('save')->with($created);

        $identities = $this->createMock(OidcIdentityRepository::class);
        $identities->method('findByProviderSubject')->willReturn(null);
        $identities->expects(self::once())->method('link')->willReturn(
            new OidcIdentity(Uuid::v7(), $createdId, OidcClient::PROVIDER, 'sub-3', 'new@example.test'),
        );

        $session = $this->arraySession();
        $service = new OidcLoginService(
            $identities,
            $users,
            new PasswordHasher(),
            $this->loginService($users, $session),
        );

        $result = $service->loginWithClaims($site, new OidcLoginPolicy(true, true, RoleSlug::MEMBER), [
            'sub' => 'sub-3',
            'email' => 'new@example.test',
            'email_verified' => true,
            'name' => 'New',
        ]);

        self::assertSame($createdId->value, $result->id->value);
        self::assertSame($createdId->value, $session->get('user_id'));
    }

    private function site(): Site
    {
        return new Site(
            new SiteId(Uuid::v7()),
            new TenantId(Uuid::v7()),
            'Test',
            'example.test',
            'de',
            LocaleUrlStrategy::Prefix,
            [],
        );
    }

    private function loginService(UserRepository $users, SessionStore $session): LoginService
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE audit_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id TEXT NULL,
                actor_id TEXT NULL,
                action TEXT NOT NULL,
                entity_type TEXT NULL,
                entity_id TEXT NULL,
                ip BLOB NULL,
                context TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $sites = $this->createMock(SiteRepository::class);
        $sites->method('installed')->willReturn(null);

        $waPdo = new PDO('sqlite::memory:');
        $waPdo->exec(
            'CREATE TABLE webauthn_credentials (
                id TEXT PRIMARY KEY,
                user_id TEXT NOT NULL,
                credential_id TEXT NOT NULL,
                public_key TEXT NOT NULL,
                sign_count INTEGER NOT NULL DEFAULT 0,
                transports TEXT NULL,
                name TEXT NOT NULL,
                created_at TEXT NOT NULL,
                last_used_at TEXT NULL
            )',
        );

        return new LoginService(
            $users,
            new PasswordHasher(),
            $session,
            new AuditLogger($pdo, new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-11-16 10:00:00.000');
                }
            }),
            new AuthThrottle(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-oidc-throttle-' . uniqid()),
            new EventDispatcher(),
            $sites,
            new WebAuthnService(
                new PdoWebAuthnCredentialRepository($waPdo),
                $session,
                new Config(['app' => ['url' => 'https://example.test']], sys_get_temp_dir()),
            ),
        );
    }

    private function arraySession(): SessionStore
    {
        return new class implements SessionStore {
            /** @var array<string, mixed> */
            private array $data = [];

            public function start(string $cookiePath, bool $secure = false): void
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }

            public function remove(string $key): void
            {
                unset($this->data[$key]);
            }

            public function regenerate(): void
            {
            }

            public function flash(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }

            public function pullFlash(string $key, mixed $default = null): mixed
            {
                $value = $this->data[$key] ?? $default;
                unset($this->data[$key]);

                return $value;
            }
        };
    }
}
