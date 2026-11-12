<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use DateTimeImmutable;
use Nexis\Audit\AuditLogger;
use Nexis\Auth\MembershipLookup;
use Nexis\Auth\PermissionLookup;
use Nexis\Auth\PolicyRegistry;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Http\Controller\BackupAdminController;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\I18n\Translator;
use Nexis\Infrastructure\Backup\BackupArchiver;
use Nexis\Infrastructure\Backup\BackupCatalog;
use Nexis\Infrastructure\Backup\BackupScheduleStore;
use Nexis\Infrastructure\Backup\LogicalBackup;
use Nexis\Kernel\Config;
use Nexis\Security\SecretBox;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;
use Nexis\Site\TenantId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase;

final class BackupAdminRestoreTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-restore-' . bin2hex(random_bytes(4));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups', 0775, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache', 0775, true);
        mkdir($this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testRestoreRequiresConfirmation(): void
    {
        $stamp = '20261112-120000';
        $this->seedBackup($stamp);

        $response = $this->controller()->restore($this->request([
            'stamp' => $stamp,
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('error=restore_confirm', $response->getHeaderLine('Location'));
    }

    public function testRestoreRejectsMissingBackup(): void
    {
        $response = $this->controller()->restore($this->request([
            'stamp' => '20261112-120000',
            'confirm' => '1',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('error=missing', $response->getHeaderLine('Location'));
    }

    public function testRestoreRejectsEmptyDatabaseDump(): void
    {
        $stamp = '20261112-130000';
        $dir = $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $stamp;
        mkdir($dir, 0775, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'database.sql', '');

        $response = $this->controller()->restore($this->request([
            'stamp' => $stamp,
            'confirm' => '1',
        ]));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('error=missing', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, string> $body
     */
    private function request(array $body): ServerRequest
    {
        $user = new User(
            new UserId(Uuid::v7()),
            'admin@example.com',
            'hash',
            'Admin',
            true,
            'de',
        );

        return (new ServerRequest('POST', '/admin/backups/restore'))
            ->withAttribute('user', $user)
            ->withAttribute('base_path', '')
            ->withAttribute('csrf', 'token')
            ->withParsedBody($body);
    }

    private function seedBackup(string $stamp): void
    {
        $dir = $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $stamp;
        mkdir($dir, 0775, true);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'database.sql', "-- nexis test dump\nSELECT 1;\n");
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode([
            'created_at' => '2026-11-12T12:00:00+00:00',
            'database' => 'nexis',
            'database_ok' => true,
        ], JSON_THROW_ON_ERROR));
    }

    private function controller(): BackupAdminController
    {
        $site = new Site(
            new SiteId(Uuid::v7()),
            new TenantId(Uuid::v7()),
            'Demo',
            'localhost',
            'de',
            LocaleUrlStrategy::None,
            [],
        );

        $sites = $this->createStub(SiteRepository::class);
        $sites->method('installed')->willReturn($site);

        $policy = new SitePolicy(
            new class implements MembershipLookup {
                public function hasAccess(UserId $userId, SiteId $siteId): bool
                {
                    return true;
                }

                public function siteIdsFor(UserId $userId): array
                {
                    return [];
                }
            },
            new class implements PermissionLookup {
                public function userHas(UserId $userId, SiteId $siteId, string $permission): bool
                {
                    return true;
                }

                public function forUserOnSite(UserId $userId, SiteId $siteId): array
                {
                    return [];
                }
            },
            new PolicyRegistry(),
        );

        $backup = new LogicalBackup($this->root);
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec(
            'CREATE TABLE audit_log (
                id INTEGER PRIMARY KEY,
                site_id TEXT,
                actor_id TEXT,
                action TEXT,
                entity_type TEXT,
                entity_id TEXT,
                ip BLOB,
                context TEXT,
                created_at TEXT
            )',
        );
        $pdo->exec(
            'CREATE TABLE site_settings (
                site_id TEXT NOT NULL,
                setting_key TEXT NOT NULL,
                setting_value TEXT,
                PRIMARY KEY (site_id, setting_key)
            )',
        );

        $psr17 = new Psr17Factory();
        $langPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang';

        return new BackupAdminController(
            new ViewRenderer(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views'),
            new ResponseFactory($psr17, $psr17),
            $sites,
            $policy,
            $backup,
            new BackupCatalog($backup),
            new BackupArchiver($backup, $this->root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp'),
            new BackupScheduleStore(new PdoSiteSettingsRepository($pdo, new SecretBox(Config::load(sys_get_temp_dir())))),
            new Config([
                'db' => [
                    'host' => '127.0.0.1',
                    'port' => '3306',
                    'database' => 'nexis',
                    'username' => 'root',
                    'password' => '',
                ],
                'app' => ['url' => 'http://localhost'],
            ], $this->root),
            new class implements Clock {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2026-11-12T12:00:00+00:00');
                }
            },
            new AuditLogger($pdo, new class implements Clock {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2026-11-12T12:00:00+00:00');
                }
            }),
            new AdminUi(new Translator($langPath, 'de', 'de')),
        );
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path) && !is_file($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removeTree($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}
