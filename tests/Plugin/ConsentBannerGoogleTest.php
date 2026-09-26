<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Kernel\Config;
use Nexis\Plugin\PluginAssetRegistry;
use Nexis\Plugins\Consent\ConsentBanner;
use Nexis\Security\SecretBox;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteLocale;
use Nexis\Site\SiteRepository;
use Nexis\Site\TenantId;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConsentBannerGoogleTest extends TestCase
{
    protected function setUp(): void
    {
        $matches = glob(dirname(__DIR__, 2) . '/plugins/*/consent/src/ConsentBanner.php') ?: [];
        if ($matches === []) {
            self::markTestSkipped('Consent plugin not installed under plugins/*/consent');
        }
        require_once $matches[0];
        $_ENV['APP_KEY'] = bin2hex(random_bytes(32));
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
    }

    public function testHeadIncludesConsentModeDefaultsAndGaConfig(): void
    {
        [$sites, $settings, $siteId] = $this->fixtures([
            'plugin:nexis/consent.enabled' => true,
            'plugin:nexis/consent.ga_measurement_id' => 'G-TEST12345',
            'plugin:nexis/consent.gtm_id' => '',
            'plugin:nexis/consent.google_ads_id' => 'AW-999',
        ]);

        $banner = new ConsentBanner($sites, $settings, $this->publicUi(), new PluginAssetRegistry());
        $head = $banner->headHtml(['basePath' => '/nexis']);

        self::assertStringContainsString('gtag("consent","default"', $head);
        self::assertStringContainsString('analytics_storage:"denied"', $head);
        self::assertStringContainsString('gtag/js?id=G-TEST12345', $head);
        self::assertStringContainsString('gtag("config","AW-999")', $head);
        self::assertStringContainsString('consent.js', $head);
        unset($siteId);
    }

    public function testBannerShowsCategoryControlsWhenGoogleConfigured(): void
    {
        [$sites, $settings] = $this->fixtures([
            'plugin:nexis/consent.enabled' => true,
            'plugin:nexis/consent.ga_measurement_id' => 'G-ABCDEF12',
            'plugin:nexis/consent.gtm_id' => '',
            'plugin:nexis/consent.google_ads_id' => '',
            'plugin:nexis/consent.privacy_url' => '/nexis/de/datenschutz',
        ]);

        $html = (new ConsentBanner($sites, $settings, $this->publicUi(), new PluginAssetRegistry()))->bannerHtml(['locale' => 'de', 'basePath' => '/nexis']);
        self::assertStringContainsString('data-nx-cat="analytics"', $html);
        self::assertStringContainsString('data-nx-consent-reject', $html);
        self::assertStringContainsString('data-nx-consent-save', $html);
        self::assertStringContainsString('data-nx-consent-accept', $html);
    }

    public function testSettingsLinkIsRenderedWhenTrackingConfigured(): void
    {
        [$sites, $settings] = $this->fixtures([
            'plugin:nexis/consent.enabled' => true,
            'plugin:nexis/consent.ga_measurement_id' => 'G-ABCDEF12',
            'plugin:nexis/consent.gtm_id' => '',
            'plugin:nexis/consent.google_ads_id' => '',
        ]);

        $html = (new ConsentBanner($sites, $settings, $this->publicUi(), new PluginAssetRegistry()))->settingsLinkHtml(['locale' => 'de']);
        self::assertStringContainsString('data-nx-consent-open', $html);
        self::assertStringContainsString('Cookie-Einstellungen', $html);
        self::assertStringContainsString('href="#cookies"', $html);
        self::assertStringContainsString('<a ', $html);
    }

    public function testNoPublicConsentUiWithoutGoogleIntegration(): void
    {
        [$sites, $settings] = $this->fixtures([
            'plugin:nexis/consent.enabled' => true,
            'plugin:nexis/consent.ga_measurement_id' => '',
            'plugin:nexis/consent.gtm_id' => '',
            'plugin:nexis/consent.google_ads_id' => '',
        ]);

        $banner = new ConsentBanner($sites, $settings, $this->publicUi(), new PluginAssetRegistry());
        self::assertSame('', $banner->headHtml(['basePath' => '/nexis']));
        self::assertSame('', $banner->bannerHtml(['locale' => 'de', 'basePath' => '/nexis']));
        self::assertSame('', $banner->settingsLinkHtml(['locale' => 'de']));
    }

    public function testTrackingConfiguredDetectsAnyGoogleId(): void
    {
        [$sites, $settings, $siteId] = $this->fixtures([
            'plugin:nexis/consent.ga_measurement_id' => 'G-TEST12345',
        ]);
        $banner = new ConsentBanner($sites, $settings, $this->publicUi(), new PluginAssetRegistry());
        $site = $sites->installed();
        self::assertNotNull($site);
        self::assertTrue($banner->trackingConfigured($site));
        unset($siteId);
    }

    /**
     * @param array<string, mixed> $values
     * @return array{0: SiteRepository, 1: PdoSiteSettingsRepository, 2: SiteId}
     */
    private function fixtures(array $values): array
    {
        $siteId = new SiteId(Uuid::v7());
        $site = new Site(
            $siteId,
            new TenantId(Uuid::v7()),
            'Demo',
            'localhost',
            'de',
            LocaleUrlStrategy::Prefix,
            [new SiteLocale('de', 'Deutsch', 'de', 'de', true, true)],
        );

        $sites = $this->createMock(SiteRepository::class);
        $sites->method('installed')->willReturn($site);

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
        $settings = new PdoSiteSettingsRepository($pdo, new SecretBox(Config::load(sys_get_temp_dir())));
        foreach ($values as $key => $value) {
            $settings->set($siteId, $key, $value);
        }

        return [$sites, $settings, $siteId];
    }

    private function publicUi(): PublicUi
    {
        $translator = new Translator(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang');
        $translator->addPath(
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis'
            . DIRECTORY_SEPARATOR . 'consent' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang',
        );

        return new PublicUi($translator);
    }
}
