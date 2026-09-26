<?php

declare(strict_types=1);

namespace Nexis\Tests\Site;

use Nexis\Kernel\Config;
use Nexis\Security\SecretBox;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class SiteSettingsEncryptionTest extends TestCase
{
    private PDO $pdo;
    private PdoSiteSettingsRepository $settings;
    private SiteId $siteId;

    protected function setUp(): void
    {
        $_ENV['APP_KEY'] = bin2hex(random_bytes(32));
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE site_settings (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                `key` TEXT NOT NULL,
                value TEXT NOT NULL,
                encrypted INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )',
        );
        $this->settings = new PdoSiteSettingsRepository(
            $this->pdo,
            new SecretBox(Config::load(sys_get_temp_dir())),
        );
        $this->siteId = new SiteId(Uuid::v7());
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testSecretKeysAreEncryptedAtRest(): void
    {
        $this->settings->set($this->siteId, 'plugin:demo/oauth.access_token', 'super-secret');
        $stmt = $this->pdo->query('SELECT value, encrypted FROM site_settings LIMIT 1');
        self::assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['encrypted']);
        self::assertStringContainsString('nx1:', (string) $row['value']);
        self::assertSame('super-secret', $this->settings->get($this->siteId, 'plugin:demo/oauth.access_token'));
    }

    public function testNormalKeysStayPlainJson(): void
    {
        $this->settings->set($this->siteId, 'i18n.missing_policy', 'hide');
        $stmt = $this->pdo->query('SELECT value, encrypted FROM site_settings LIMIT 1');
        self::assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(0, (int) $row['encrypted']);
        self::assertSame('"hide"', $row['value']);
        self::assertSame('hide', $this->settings->get($this->siteId, 'i18n.missing_policy'));
    }
}
