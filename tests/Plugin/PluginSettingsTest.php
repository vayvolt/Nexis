<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Kernel\Config;
use Nexis\Plugin\SettingsSchemaRegistry;
use Nexis\Security\SecretBox;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class PluginSettingsTest extends TestCase
{
    use PluginKernelTestFactory;

    private PDO $pdo;
    private PdoSiteSettingsRepository $store;
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
        $this->store = new PdoSiteSettingsRepository(
            $this->pdo,
            new SecretBox(Config::load(sys_get_temp_dir())),
        );
        $this->siteId = new SiteId(Uuid::v7());
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testRegisterSettingsAndRoundTrip(): void
    {
        $schema = new SettingsSchemaRegistry();
        $kernel = $this->makePluginKernel(settingsSchema: $schema, siteSettings: $this->store);
        $kernel->forPlugin('acme/shop');
        $kernel->registerSettings([
            'notify_email' => ['type' => 'email', 'default' => ''],
            'api_token' => ['type' => 'secret', 'default' => ''],
        ]);

        $bag = $kernel->pluginSettings();
        $bag->set($this->siteId, 'notify_email', 'a@b.test');
        $bag->set($this->siteId, 'api_token', 'tok-secret');

        self::assertSame('a@b.test', $bag->get($this->siteId, 'notify_email'));
        self::assertSame('tok-secret', $bag->get($this->siteId, 'api_token'));
        self::assertSame('plugin:acme/shop.', $schema->storagePrefix('acme/shop'));

        $stmt = $this->pdo->prepare('SELECT encrypted FROM site_settings WHERE `key` = :key');
        $stmt->execute(['key' => 'plugin:acme/shop.api_token']);
        self::assertSame(1, (int) $stmt->fetchColumn());
    }
}
