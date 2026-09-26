<?php

declare(strict_types=1);

namespace Nexis\Tests\Auth;

use Nexis\Auth\AuthSettings;
use Nexis\Auth\PasswordHasher;
use Nexis\Auth\PasswordResetService;
use Nexis\Auth\PdoPasswordResetRepository;
use Nexis\Auth\PdoUserRepository;
use Nexis\Auth\RoleSlug;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Kernel\Bootstrap;
use Nexis\Mail\MailMessage;
use Nexis\Mail\MailPort;
use PHPUnit\Framework\TestCase;

/**
 * Integration-style tests when DB is available; otherwise skip.
 */
final class AccountAuthTest extends TestCase
{
    public function testRoleSlugMemberConstant(): void
    {
        self::assertSame('member', RoleSlug::MEMBER);
    }

    public function testPasswordResetFlowPersistsHashedToken(): void
    {
        if (!$this->dbAvailable()) {
            self::markTestSkipped('Database not available');
        }
        $app = Bootstrap::boot(dirname(__DIR__, 2));
        $pdo = $app->container->get(\PDO::class);
        $users = $app->container->get(PdoUserRepository::class);
        $tokens = $app->container->get(PdoPasswordResetRepository::class);
        $hasher = $app->container->get(PasswordHasher::class);
        $settings = $app->container->get(AuthSettings::class);
        $sites = $app->container->get(\Nexis\Site\SiteRepository::class);
        $site = $sites->installed();
        if ($site === null) {
            self::markTestSkipped('No site installed');
        }

        $email = 'reset-' . bin2hex(random_bytes(4)) . '@nexis.test';
        $user = $users->createUser($email, $hasher->hash('password123'), 'Reset User', false, 'de');
        $roleId = $users->ensureMemberRole($site->id);
        $users->setMembership($site->id, $user->id, $roleId);
        $settings->setPasswordResetEnabled($site->id, true);

        $mail = new class implements MailPort {
            public ?MailMessage $last = null;

            public function send(MailMessage $message): void
            {
                $this->last = $message;
            }
        };

        $service = new PasswordResetService(
            $users,
            $users,
            $hasher,
            $tokens,
            $settings,
            $mail,
            new PublicUi(new Translator(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang')),
        );
        $service->request($site, $email, 'http://localhost/nexis', '/nexis');
        self::assertNotNull($mail->last);
        self::assertStringContainsString('/account/password/reset?token=', $mail->last->textBody);
        $matched = preg_match('#token=([a-f0-9]{64})#', $mail->last->textBody, $m);
        self::assertSame(1, $matched);
        $raw = is_string($m[1] ?? null) ? $m[1] : '';
        self::assertSame(64, strlen($raw));

        $stmt = $pdo->prepare('SELECT token_hash FROM password_reset_tokens WHERE user_id = :id AND used_at IS NULL');
        $stmt->execute(['id' => $user->id->value]);
        $hash = $stmt->fetchColumn();
        self::assertSame(hash('sha256', $raw), $hash);

        $service->reset($raw, 'new-password-99');
        $reloaded = $users->findByEmail($email);
        self::assertNotNull($reloaded);
        self::assertTrue($hasher->verify('new-password-99', $reloaded->passwordHash));
    }

    private function dbAvailable(): bool
    {
        try {
            $root = dirname(__DIR__, 2);
            if (!is_file($root . DIRECTORY_SEPARATOR . '.env')) {
                return false;
            }
            $app = Bootstrap::boot($root);
            $app->container->get(\PDO::class)->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
