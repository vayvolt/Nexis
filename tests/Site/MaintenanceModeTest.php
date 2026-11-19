<?php

declare(strict_types=1);

namespace Nexis\Tests\Site;

use Nexis\Kernel\Config;
use Nexis\Security\SecretBox;
use Nexis\Site\MaintenanceMode;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class MaintenanceModeTest extends TestCase
{
    public function testEnabledAndMessageRoundTrip(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE site_settings (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                `key` TEXT NOT NULL,
                value TEXT NULL,
                encrypted INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL,
                UNIQUE(site_id, `key`)
            )',
        );

        $box = new SecretBox(Config::load(sys_get_temp_dir()));
        $settings = new PdoSiteSettingsRepository($pdo, $box);
        $mode = new MaintenanceMode($settings);
        $siteId = new SiteId(Uuid::v7());

        self::assertFalse($mode->isEnabled($siteId));
        self::assertSame('', $mode->message($siteId));

        $mode->setEnabled($siteId, true);
        $mode->setMessage($siteId, '  Back soon  ');
        self::assertTrue($mode->isEnabled($siteId));
        self::assertSame('Back soon', $mode->message($siteId));
    }
}
