<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\AuthThrottle;
use Nexis\Auth\LoginService;
use Nexis\Auth\PasswordHasher;
use Nexis\Auth\PdoUserInviteRepository;
use Nexis\Auth\PdoWebAuthnCredentialRepository;
use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Auth\UserInviteService;
use Nexis\Auth\UserRepository;
use Nexis\Auth\WebAuthnService;
use Nexis\Event\EventDispatcher;
use Nexis\Http\SessionStore;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Kernel\Config;
use Nexis\Mail\MailMessage;
use Nexis\Mail\MailPort;
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

final class UserInviteServiceTest extends TestCase
{
    public function testAcceptCreatesUserAndLogsIn(): void
    {
        $pdo = $this->pdo();
        $site = $this->site();
        $actor = new User(new UserId(Uuid::v7()), 'admin@example.test', 'hash', 'Admin', true, 'de');
        $roleId = Uuid::v7();

        $createdId = new UserId(Uuid::v7());
        $created = new User($createdId, 'editor@example.test', 'hash', 'Editor', false, 'de');

        $users = $this->createMock(UserRepository::class);
        $users->method('findByEmail')->willReturnOnConsecutiveCalls(null, null);
        $users->expects(self::once())->method('createUser')->willReturn($created);
        $users->expects(self::once())->method('setMembership')->with($site->id, $createdId, $roleId);
        $users->expects(self::once())->method('markEmailVerified')->with($createdId);
        $users->expects(self::once())->method('save')->with($created);
        $users->method('findById')->willReturn($created);

        $mail = new class implements MailPort {
            public ?MailMessage $last = null;

            public function send(MailMessage $message): void
            {
                $this->last = $message;
            }
        };

        $session = $this->arraySession();
        $sites = $this->createMock(SiteRepository::class);
        $sites->method('findById')->willReturn($site);
        $sites->method('installed')->willReturn($site);

        $invites = new PdoUserInviteRepository($pdo);
        $service = new UserInviteService(
            $users,
            $invites,
            new PasswordHasher(),
            $mail,
            new PublicUi(new Translator(dirname(__DIR__, 2) . '/resources/lang')),
            new AuditLogger($pdo, new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-11-16 12:00:00.000');
                }
            }),
            $this->loginService($users, $session, $sites, $pdo),
            $sites,
        );

        $createdInvite = $service->invite(
            $site,
            $actor,
            'editor@example.test',
            'Editor',
            $roleId,
            false,
            'https://example.test',
            '',
        );
        self::assertNotNull($mail->last);
        self::assertStringContainsString('/account/invite?token=', $mail->last->textBody);
        self::assertNotNull($mail->last->htmlBody);
        self::assertStringContainsString('Konto aktivieren', (string) $mail->last->htmlBody);
        self::assertStringContainsString('Nexis', (string) $mail->last->htmlBody);

        $user = $service->accept($createdInvite['raw'], 'Editor', 'password123');
        self::assertSame($createdId->value, $user->id->value);
        self::assertSame($createdId->value, $session->get('user_id'));
        self::assertNull($invites->findValid($createdInvite['raw']));
    }

    public function testRejectsInvalidToken(): void
    {
        $pdo = $this->pdo();
        $users = $this->createMock(UserRepository::class);
        $sites = $this->createMock(SiteRepository::class);
        $service = new UserInviteService(
            $users,
            new PdoUserInviteRepository($pdo),
            new PasswordHasher(),
            new class implements MailPort {
                public function send(MailMessage $message): void
                {
                }
            },
            new PublicUi(new Translator(dirname(__DIR__, 2) . '/resources/lang')),
            new AuditLogger($pdo, new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-11-16 12:00:00.000');
                }
            }),
            $this->loginService($users, $this->arraySession(), $sites, $pdo),
            $sites,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('auth.invite_invalid');
        $service->accept('deadbeef', 'Name', 'password123');
    }

    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE user_invites (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                email TEXT NOT NULL,
                display_name TEXT NOT NULL,
                role_id TEXT NOT NULL,
                is_platform_admin INTEGER NOT NULL DEFAULT 0,
                token_hash TEXT NOT NULL,
                invited_by TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                accepted_at TEXT NULL,
                created_at TEXT NOT NULL
            )',
        );
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
        $pdo->exec(
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

        return $pdo;
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

    private function loginService(UserRepository $users, SessionStore $session, SiteRepository $sites, PDO $pdo): LoginService
    {
        return new LoginService(
            $users,
            new PasswordHasher(),
            $session,
            new AuditLogger($pdo, new class implements Clock {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-11-16 12:00:00.000');
                }
            }),
            new AuthThrottle(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-invite-throttle-' . uniqid()),
            new EventDispatcher(),
            $sites,
            new WebAuthnService(
                new PdoWebAuthnCredentialRepository($pdo),
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
