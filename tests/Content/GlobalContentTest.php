<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\GlobalContent;
use Nexis\Kernel\Config;
use Nexis\Security\SecretBox;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteLocale;
use Nexis\Site\TenantId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class GlobalContentTest extends TestCase
{
    public function testRendersCtaAndTeaserWithLocaleFallback(): void
    {
        $_ENV['APP_KEY'] = bin2hex(random_bytes(32));
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE site_settings (
                id TEXT PRIMARY KEY,
                site_id TEXT NOT NULL,
                `key` TEXT NOT NULL,
                value TEXT NOT NULL,
                encrypted INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )',
        );
        $settings = new PdoSiteSettingsRepository(
            $pdo,
            new SecretBox(Config::load(sys_get_temp_dir())),
        );
        $siteId = new SiteId(Uuid::v7());
        $globals = new GlobalContent($settings);
        $globals->saveMaps(
            $siteId,
            ['de' => 'Kontakt', 'en' => 'Contact'],
            ['de' => '/de/kontakt', 'en' => 'https://example.test/en/contact'],
            ['de' => 'Fragen? Schreib uns.', 'en' => ''],
        );

        $site = new Site(
            $siteId,
            new TenantId(Uuid::v7()),
            'Demo',
            'example.test',
            'de',
            LocaleUrlStrategy::Prefix,
            [
                new SiteLocale('de', 'Deutsch', null, 'de', true, true),
                new SiteLocale('en', 'English', null, 'en', false, true),
            ],
        );

        $ctaDe = $globals->renderHeaderCta($site, 'de', '/nexis');
        self::assertStringContainsString('bk-global-cta', $ctaDe);
        self::assertStringContainsString('Kontakt', $ctaDe);
        self::assertStringContainsString('href="/nexis/de/kontakt"', $ctaDe);

        $ctaEn = $globals->renderHeaderCta($site, 'en', '/nexis');
        self::assertStringContainsString('Contact', $ctaEn);
        self::assertStringContainsString('https://example.test/en/contact', $ctaEn);

        self::assertStringContainsString('Fragen?', $globals->renderFooterTeaser($site, 'de'));
        self::assertStringContainsString('Fragen?', $globals->renderFooterTeaser($site, 'en'));

        $globals->saveMaps($siteId, ['de' => ''], ['de' => ''], ['de' => '']);
        self::assertSame('', $globals->renderHeaderCta($site, 'de', '/nexis'));
        self::assertSame('', $globals->renderFooterTeaser($site, 'de'));
    }
}
