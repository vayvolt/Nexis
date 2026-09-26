<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Auth\PolicyRegistry;
use Nexis\Builder\BlockRegistry;
use Nexis\Cache\PageCacheBypassRegistry;
use Nexis\Content\PrimaryNavLinkRegistry;
use Nexis\Content\PrimaryNavLinkSync;
use Nexis\Event\EventDispatcher;
use Nexis\Http\CsrfExemptRegistry;
use Nexis\Http\RouteCollector;
use Nexis\Http\SitemapPathRegistry;
use Nexis\Kernel\Config;
use Nexis\Mail\MailJobRegistry;
use Nexis\Plugin\PluginKernel;
use Nexis\Plugin\SettingsSchemaRegistry;
use Nexis\Queue\JobHandlerRegistry;
use Nexis\Security\SecretBox;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Theme\TwigExtensionRegistry;
use Nexis\Webhook\WebhookEventRegistry;
use PDO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

trait PluginKernelTestFactory
{
    private function makePluginKernel(
        ?LoggerInterface $logger = null,
        ?MailJobRegistry $mailJobs = null,
        ?SitemapPathRegistry $sitemap = null,
        ?WebhookEventRegistry $webhooks = null,
        ?PageCacheBypassRegistry $bypass = null,
        ?TwigExtensionRegistry $twig = null,
        ?EventDispatcher $events = null,
        ?SettingsSchemaRegistry $settingsSchema = null,
        ?PdoSiteSettingsRepository $siteSettings = null,
        ?PolicyRegistry $policies = null,
        ?CsrfExemptRegistry $csrfExempt = null,
    ): PluginKernel {
        $logger ??= new NullLogger();
        $sites = $this->createStub(\Nexis\Site\SiteRepository::class);
        $sites->method('installed')->willReturn(null);
        $navSync = new PrimaryNavLinkSync(
            $sites,
            $this->createStub(\Nexis\Content\MenuRepository::class),
            new \Nexis\Site\LocalePathResolver(),
        );
        if ($siteSettings === null) {
            if (!isset($_ENV['APP_KEY']) || $_ENV['APP_KEY'] === '') {
                $_ENV['APP_KEY'] = bin2hex(random_bytes(32));
            }
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
            $siteSettings = new PdoSiteSettingsRepository(
                $pdo,
                new SecretBox(Config::load(sys_get_temp_dir())),
            );
        }

        return new PluginKernel(
            new BlockRegistry([]),
            new RouteCollector(),
            $logger,
            $mailJobs ?? new MailJobRegistry(),
            new JobHandlerRegistry($logger),
            $bypass ?? new PageCacheBypassRegistry(),
            $webhooks ?? new WebhookEventRegistry(),
            $events ?? new EventDispatcher(),
            $sitemap ?? new SitemapPathRegistry(),
            new PrimaryNavLinkRegistry($navSync, $sites),
            $twig ?? new TwigExtensionRegistry(),
            $settingsSchema ?? new SettingsSchemaRegistry(),
            $siteSettings,
            $policies ?? new PolicyRegistry(),
            $csrfExempt ?? new CsrfExemptRegistry(),
        );
    }
}
