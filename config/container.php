<?php

declare(strict_types=1);

use Nexis\Auth\AdminMembershipGuard;
use Nexis\Auth\AuthThrottle;
use Nexis\Auth\LoginService;
use Nexis\Auth\MembershipLookup;
use Nexis\Auth\PasswordHasher;
use Nexis\Auth\PdoPasswordResetRepository;
use Nexis\Auth\PdoPermissionLookup;
use Nexis\Auth\PdoUserRepository;
use Nexis\Auth\PermissionLookup;
use Nexis\Auth\AuthSettings;
use Nexis\Auth\PasswordResetService;
use Nexis\Auth\RegistrationService;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\SystemRoleSeeder;
use Nexis\Auth\UserRepository;
use Nexis\Mail\EnvMailPort;
use Nexis\Mail\MailJobRegistry;
use Nexis\Mail\MailLogRepository;
use Nexis\Mail\MailPort;
use Nexis\Mail\MailQueueWorker;
use Nexis\Mail\PdoMailLogRepository;
use Nexis\Mail\WorkshopOrderMailHandler;
use Nexis\Builder\BlockRegistry;
use Nexis\Builder\BlockRenderer;
use Nexis\Builder\Core\ButtonBlock;
use Nexis\Builder\Core\ColumnsBlock;
use Nexis\Builder\Core\EmbedBlock;
use Nexis\Builder\Core\FaqAccordionBlock;
use Nexis\Builder\Core\FaqItemBlock;
use Nexis\Builder\Core\GalleryBlock;
use Nexis\Builder\Core\HeadingBlock;
use Nexis\Builder\Core\ImageBlock;
use Nexis\Builder\Core\LocationPlaceBlock;
use Nexis\Builder\Core\SectionBlock;
use Nexis\Builder\Core\TableBlock;
use Nexis\Builder\Core\TextBlock;
use Nexis\Builder\DocumentService;
use Nexis\Builder\DocumentValidator;
use Nexis\Builder\PatternRepository;
use Nexis\Builder\PdoPatternRepository;
use Nexis\Builder\PdoRevisionRepository;
use Nexis\Builder\PdoSnapshotRepository;
use Nexis\Builder\PublishService;
use Nexis\Builder\RevisionRepository;
use Nexis\Builder\SnapshotRepository;
use Nexis\Content\GlobalContent;
use Nexis\Content\MenuRepository;
use Nexis\Content\PageEditorialRepository;
use Nexis\Content\PageRepository;
use Nexis\Content\PdoMenuRepository;
use Nexis\Content\PdoPageEditorialRepository;
use Nexis\Content\PdoPageRepository;
use Nexis\Content\ScheduledPublishWorker;
use Nexis\Http\Controller\BuilderAdminController;
use Nexis\Http\Controller\AboutAdminController;
use Nexis\Http\Controller\AccountController;
use Nexis\Http\Controller\UiLocaleAdminController;
use Nexis\Http\Controller\DashboardController;
use Nexis\Http\Controller\HealthController;
use Nexis\Http\Controller\HealthAdminController;
use Nexis\Http\Controller\LoginController;
use Nexis\Http\Controller\MailLogAdminController;
use Nexis\Http\Controller\LogsAdminController;
use Nexis\Http\Controller\MenuAdminController;
use Nexis\Http\Controller\MediaAdminController;
use Nexis\Http\Controller\GlobalContentAdminController;
use Nexis\Http\Controller\PatternAdminController;
use Nexis\Http\Controller\AuditAdminController;
use Nexis\Http\Controller\MediaServeController;
use Nexis\Http\Controller\PageAdminController;
use Nexis\Http\Controller\PluginAdminController;
use Nexis\Http\Controller\PluginSettingsAdminController;
use Nexis\Http\Controller\PublicPageController;
use Nexis\Http\Controller\RobotsTxtController;
use Nexis\Http\Controller\SettingsAdminController;
use Nexis\Http\Controller\UserAdminController;
use Nexis\Http\Controller\SearchController;
use Nexis\Http\Controller\SecurityAdminController;
use Nexis\Http\Controller\SitemapController;
use Nexis\Http\Controller\ThemeAdminController;
use Nexis\Http\Controller\WebhookAdminController;
use Nexis\Http\Controller\ExportAdminController;
use Nexis\Audit\AuditLogger;
use Nexis\Cache\PageCache;
use Nexis\Event\EventDispatcher;
use Nexis\Event\PagePublished;
use Nexis\Event\PageReviewRejected;
use Nexis\Event\PageSubmittedForReview;
use Nexis\Event\PageUnpublished;
use Nexis\Event\PluginToggled;
use Nexis\Plugin\BlogMenuSync;
use Nexis\Plugin\CatalogMenuSync;
use Nexis\Http\Controller\SignedPreviewController;
use Nexis\Http\Middleware\RateLimitMiddleware;
use Nexis\Http\Middleware\SecurityHeadersMiddleware;
use Nexis\Http\SignedUrl;
use Nexis\Plugin\PluginSignatureVerifier;
use Nexis\Queue\JobQueue;
use Nexis\Search\MariaDbFulltextSearch;
use Nexis\Search\SearchPort;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteExporter;
use Nexis\Site\SiteImporter;
use Nexis\Webhook\CurlWebhookClient;
use Nexis\Webhook\WebhookClient;
use Nexis\Webhook\WebhookDispatcher;
use Nexis\Webhook\WebhookRepository;
use Nexis\Plugin\PluginAssetPublisher;
use Nexis\Plugin\PluginAssetRegistry;
use Nexis\Theme\ThemeAssetPublisher;
use Nexis\Theme\ThemeCatalog;
use Nexis\Theme\ThemeDiscovery;
use Nexis\Theme\ThemeManifestLoader;
use Nexis\Theme\ThemeService;
use Nexis\Theme\ThemeViewRenderer;
use Nexis\Theme\TokenResolver;
use Nexis\Http\Csrf;
use Nexis\Http\AdminContentLocale;
use Nexis\Http\HttpKernel;
use Nexis\I18n\Translator;
use Nexis\I18n\PublicUi;
use Nexis\Http\Middleware\MaintenanceMiddleware;
use Nexis\Http\Middleware\CsrfMiddleware;
use Nexis\Http\Middleware\ErrorHandlerMiddleware;
use Nexis\Http\Middleware\PluginPreRouteMiddleware;
use Nexis\Http\Middleware\RequestIdMiddleware;
use Nexis\Http\Middleware\RequireAuthMiddleware;
use Nexis\Http\Middleware\ResolveSiteMiddleware;
use Nexis\Http\Middleware\SessionMiddleware;
use Nexis\Http\Middleware\StripBasePathMiddleware;
use Nexis\Http\MiddlewarePipeline;
use Nexis\Http\PublicErrorRenderer;
use Nexis\Http\IdempotencyStore;
use Nexis\Http\NativeSessionStore;
use Nexis\Http\RequestSecurity;
use Nexis\Http\ResponseFactory;
use Nexis\Http\Route;
use Nexis\Http\RouteCollector;
use Nexis\Http\Router;
use Nexis\Http\SitemapArchiveProvider;
use Nexis\Http\SitemapPathRegistry;
use Nexis\Http\SessionStore;
use Nexis\Http\ViewRenderer;
use Nexis\Infrastructure\Database\ConnectionFactory;
use Nexis\Infrastructure\Database\DatabaseHealth;
use Nexis\Infrastructure\Database\Migrator;
use Nexis\Infrastructure\Health\SystemHealthReport;
use Nexis\Infrastructure\Logging\LogFileReader;
use Nexis\Infrastructure\Logging\LoggerFactory;
use Nexis\Kernel\Config;
use Nexis\Media\MediaLibrary;
use Nexis\Media\MediaRepository;
use Nexis\Media\PdoMediaRepository;
use Nexis\Plugin\ManifestLoader;
use Nexis\Plugin\MarketplaceClient;
use Nexis\Plugin\PluginCatalog;
use Nexis\Plugin\PluginDiscovery;
use Nexis\Plugin\PluginKernel;
use Nexis\Plugin\PluginMigrator;
use Nexis\Plugin\PluginPackageInstaller;
use Nexis\Plugin\PluginRuntime;
use Nexis\Plugin\PluginUninstaller;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\MaintenanceMode;
use Nexis\Site\PdoSiteRepository;
use Nexis\Site\SiteRepository;
use Nexis\Support\Clock;
use Nexis\Support\SystemClock;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

use function DI\autowire;
use function DI\factory;
use function DI\get;

return [
    Clock::class => autowire(SystemClock::class),
    Config::class => factory(static function (ContainerInterface $container): Config {
        /** @var string $root */
        $root = $container->get('app.root');

        return Config::load($root);
    }),
    Psr17Factory::class => autowire(),
    ResponseFactoryInterface::class => get(Psr17Factory::class),
    StreamFactoryInterface::class => get(Psr17Factory::class),
    LoggerInterface::class => factory(static function (ContainerInterface $container): Logger {
        /** @var string $root */
        $root = $container->get('app.root');

        return LoggerFactory::create($root, $container->get(Config::class));
    }),
    PDO::class => factory(static function (Config $config): PDO {
        return ConnectionFactory::create($config);
    }),
    DatabaseHealth::class => factory(static function (ContainerInterface $container): DatabaseHealth {
        return new DatabaseHealth(static fn (): PDO => $container->get(PDO::class));
    }),
    Migrator::class => factory(static function (ContainerInterface $container): Migrator {
        /** @var string $root */
        $root = $container->get('app.root');

        return new Migrator(
            $container->get(PDO::class),
            $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'core_schema.sql',
        );
    }),
    SessionStore::class => factory(static function (ContainerInterface $container): NativeSessionStore {
        /** @var string $root */
        $root = $container->get('app.root');

        return new NativeSessionStore($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions');
    }),
    Csrf::class => autowire(),
    AdminContentLocale::class => autowire(),
    Translator::class => factory(static function (ContainerInterface $container): Translator {
        /** @var string $root */
        $root = $container->get('app.root');

        return new Translator($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang');
    }),
    ViewRenderer::class => factory(static function (ContainerInterface $container): ViewRenderer {
        /** @var string $root */
        $root = $container->get('app.root');

        return new ViewRenderer(
            $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views',
            $container,
        );
    }),
    PasswordHasher::class => autowire(),
    PdoUserRepository::class => autowire(),
    UserRepository::class => get(PdoUserRepository::class),
    MembershipLookup::class => get(PdoUserRepository::class),
    SystemRoleSeeder::class => autowire(),
    AdminMembershipGuard::class => autowire(),
    PublicErrorRenderer::class => autowire(),
    MaintenanceMode::class => autowire(),
    LogFileReader::class => autowire(),
    SystemHealthReport::class => autowire(),
    PdoPermissionLookup::class => autowire(),
    PermissionLookup::class => get(PdoPermissionLookup::class),
    SitePolicy::class => autowire(),
    MailPort::class => autowire(EnvMailPort::class),
    MailLogRepository::class => autowire(PdoMailLogRepository::class),
    MailJobRegistry::class => factory(static function (ContainerInterface $container): MailJobRegistry {
        $registry = new MailJobRegistry();
        $registry->register('workshop_order', $container->get(WorkshopOrderMailHandler::class));

        return $registry;
    }),
    MailQueueWorker::class => autowire(),
    LoginService::class => autowire(),
    AuthThrottle::class => factory(static function (ContainerInterface $container): AuthThrottle {
        /** @var string $root */
        $root = $container->get('app.root');

        return new AuthThrottle(
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'auth-throttle',
        );
    }),
    HealthController::class => factory(static function (ContainerInterface $container): HealthController {
        /** @var string $root */
        $root = $container->get('app.root');

        return new HealthController(
            $container->get(ResponseFactory::class),
            $container->get(DatabaseHealth::class),
            $container->get(JobQueue::class),
            $root . DIRECTORY_SEPARATOR . 'storage',
        );
    }),
    AuthSettings::class => autowire(),
    PdoPasswordResetRepository::class => autowire(),
    RegistrationService::class => autowire(),
    PasswordResetService::class => autowire(),
    SiteRepository::class => autowire(PdoSiteRepository::class),
    PdoSiteSettingsRepository::class => autowire(),
    PageRepository::class => autowire(PdoPageRepository::class),
    MenuRepository::class => autowire(PdoMenuRepository::class),
    MediaRepository::class => autowire(PdoMediaRepository::class),
    PatternRepository::class => autowire(PdoPatternRepository::class),
    PageEditorialRepository::class => autowire(PdoPageEditorialRepository::class),
    GlobalContent::class => autowire(),
    RevisionRepository::class => autowire(PdoRevisionRepository::class),
    SnapshotRepository::class => autowire(PdoSnapshotRepository::class),
    BlockRegistry::class => factory(static function (): BlockRegistry {
        return new BlockRegistry([
            new SectionBlock(),
            new HeadingBlock(),
            new TextBlock(),
            new ImageBlock(),
            new GalleryBlock(),
            new EmbedBlock(),
            new TableBlock(),
            new ButtonBlock(),
            new ColumnsBlock(),
            new FaqAccordionBlock(),
            new FaqItemBlock(),
            new LocationPlaceBlock(),
        ]);
    }),
    DocumentValidator::class => autowire(),
    BlockRenderer::class => autowire(),
    DocumentService::class => autowire(),
    PublishService::class => autowire(),
    ScheduledPublishWorker::class => autowire(),
    ManifestLoader::class => autowire(),
    PluginDiscovery::class => factory(static function (ContainerInterface $container): PluginDiscovery {
        /** @var string $root */
        $root = $container->get('app.root');

        return new PluginDiscovery(
            $root . DIRECTORY_SEPARATOR . 'plugins',
            $container->get(ManifestLoader::class),
        );
    }),
    PluginCatalog::class => autowire(),
    SitemapPathRegistry::class => autowire(),
    SitemapArchiveProvider::class => get(SitemapPathRegistry::class),
    PluginMigrator::class => autowire(),
    PluginPackageInstaller::class => factory(static function (ContainerInterface $container): PluginPackageInstaller {
        /** @var string $root */
        $root = $container->get('app.root');

        return new PluginPackageInstaller(
            $root . DIRECTORY_SEPARATOR . 'plugins',
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp',
            $container->get(ManifestLoader::class),
            $container->get(PluginSignatureVerifier::class),
            $container->get(PluginCatalog::class),
        );
    }),
    PluginUninstaller::class => factory(static function (ContainerInterface $container): PluginUninstaller {
        return new PluginUninstaller(
            $container->get(PluginDiscovery::class),
            $container->get(PluginCatalog::class),
            $container->get(EventDispatcher::class),
            $container,
            $container->get(LoggerInterface::class),
            (string) $container->get('app.root'),
            $container->get(PdoPermissionLookup::class),
            $container->get(PdoSiteSettingsRepository::class),
            $container->get(\Nexis\Plugin\SettingsSchemaRegistry::class),
            $container->get(PluginAssetPublisher::class),
        );
    }),
    PluginKernel::class => autowire(),
    MarketplaceClient::class => factory(static function (ContainerInterface $container): MarketplaceClient {
        /** @var \Nexis\Kernel\Config $config */
        $config = $container->get(\Nexis\Kernel\Config::class);

        return new MarketplaceClient(
            MarketplaceClient::DIRECTORY_URL,
            $container->get(LoggerInterface::class),
            \Nexis\Kernel\Nexis::VERSION,
            $config->rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache',
        );
    }),
    PluginRuntime::class => autowire(),
    ThemeManifestLoader::class => autowire(),
    ThemeDiscovery::class => factory(static function (ContainerInterface $container): ThemeDiscovery {
        /** @var string $root */
        $root = $container->get('app.root');

        return new ThemeDiscovery(
            $root . DIRECTORY_SEPARATOR . 'themes',
            $container->get(ThemeManifestLoader::class),
        );
    }),
    ThemeCatalog::class => autowire(),
    TokenResolver::class => autowire(),
    ThemeAssetPublisher::class => factory(static function (ContainerInterface $container): ThemeAssetPublisher {
        return new ThemeAssetPublisher((string) $container->get('app.root'));
    }),
    PluginAssetPublisher::class => factory(static function (ContainerInterface $container): PluginAssetPublisher {
        return new PluginAssetPublisher((string) $container->get('app.root'));
    }),
    PluginAssetRegistry::class => autowire(),
    ThemeService::class => autowire(),
    ThemeViewRenderer::class => autowire(),
    IdempotencyStore::class => autowire(),
    AuditLogger::class => autowire(),
    SignedUrl::class => autowire(),
    JobQueue::class => autowire(),
    WebhookRepository::class => autowire(),
    WebhookClient::class => autowire(CurlWebhookClient::class),
    WebhookDispatcher::class => autowire(),
    PluginSignatureVerifier::class => autowire(),
    SearchPort::class => autowire(MariaDbFulltextSearch::class),
    SiteExporter::class => factory(static function (ContainerInterface $container): SiteExporter {
        /** @var string $root */
        $root = $container->get('app.root');

        return new SiteExporter(
            $container->get(SiteRepository::class),
            $container->get(PageRepository::class),
            $container->get(MediaRepository::class),
            $container->get(SnapshotRepository::class),
            $container->get(ThemeCatalog::class),
            $container->get(PdoSiteSettingsRepository::class),
            $container->get(PDO::class),
            $container->get(Clock::class),
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media',
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports',
        );
    }),
    SiteImporter::class => factory(static function (ContainerInterface $container): SiteImporter {
        /** @var string $root */
        $root = $container->get('app.root');

        return new SiteImporter(
            $container->get(SiteRepository::class),
            $container->get(PageRepository::class),
            $container->get(MediaRepository::class),
            $container->get(DocumentService::class),
            $container->get(RevisionRepository::class),
            $container->get(PublishService::class),
            $container->get(PageCache::class),
            $container->get(ThemeCatalog::class),
            $container->get(MenuRepository::class),
            $container->get(PdoSiteSettingsRepository::class),
            $container->get(MediaLibrary::class),
            $container->get(PDO::class),
        );
    }),
    EventDispatcher::class => factory(static function (ContainerInterface $container): EventDispatcher {
        $dispatcher = new EventDispatcher();
        $webhooks = $container->get(WebhookDispatcher::class);
        $dispatcher->listen(PagePublished::class, $webhooks->onPagePublished(...));
        $dispatcher->listen(PageUnpublished::class, $webhooks->onPageUnpublished(...));
        $dispatcher->listen(PageSubmittedForReview::class, $webhooks->onPageSubmittedForReview(...));
        $dispatcher->listen(PageReviewRejected::class, $webhooks->onPageReviewRejected(...));
        $dispatcher->listen(PluginToggled::class, $webhooks->onPluginToggled(...));
        $dispatcher->listen(PluginToggled::class, $container->get(\Nexis\Content\PrimaryNavLinkRegistry::class)->onPluginToggled(...));
        $dispatcher->listen(PluginToggled::class, static function (PluginToggled $event) use ($container): void {
            // Enable without boot this request: first-party fallbacks still sync menus.
            if ($event->pluginKey === BlogMenuSync::PLUGIN_KEY) {
                $container->get(BlogMenuSync::class)->sync($event->siteId, $event->enabled);
            }
            if ($event->pluginKey === CatalogMenuSync::PLUGIN_KEY) {
                $container->get(CatalogMenuSync::class)->sync($event->siteId, $event->enabled);
            }
        });

        return $dispatcher;
    }),
    PageCache::class => factory(static function (ContainerInterface $container): PageCache {
        /** @var string $root */
        $root = $container->get('app.root');

        return new PageCache(
            $container->get(PDO::class),
            $container->get(Clock::class),
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'pages',
        );
    }),
    LocalePathResolver::class => autowire(),
    MediaLibrary::class => factory(static function (ContainerInterface $container): MediaLibrary {
        /** @var string $root */
        $root = $container->get('app.root');
        /** @var Config $config */
        $config = $container->get(Config::class);

        return new MediaLibrary(
            $container->get(MediaRepository::class),
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'media',
            (int) $config->get('media.max_bytes', 10_485_760),
            (int) $config->get('media.max_pixels', 25_000_000),
            $container->get(EventDispatcher::class),
        );
    }),
    RateLimitMiddleware::class => factory(static function (ContainerInterface $container): RateLimitMiddleware {
        /** @var string $root */
        $root = $container->get('app.root');

        return new RateLimitMiddleware(
            $container->get(ResponseFactory::class),
            $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'rate-limit',
            $container->get(RequestSecurity::class),
            $container->get(PublicUi::class),
        );
    }),
    RouteCollector::class => factory(static function (ContainerInterface $container): RouteCollector {
        $login = $container->get(LoginController::class);
        $pages = $container->get(PageAdminController::class);
        $media = $container->get(MediaAdminController::class);
        $patterns = $container->get(PatternAdminController::class);
        $globals = $container->get(GlobalContentAdminController::class);
        $builder = $container->get(BuilderAdminController::class);
        $plugins = $container->get(PluginAdminController::class);
        $pluginSettings = $container->get(PluginSettingsAdminController::class);
        $theme = $container->get(ThemeAdminController::class);
        $security = $container->get(SecurityAdminController::class);
        $webhooks = $container->get(WebhookAdminController::class);
        $export = $container->get(ExportAdminController::class);
        $menus = $container->get(MenuAdminController::class);
        $users = $container->get(UserAdminController::class);
        $settings = $container->get(SettingsAdminController::class);
        $audit = $container->get(AuditAdminController::class);
        $about = $container->get(AboutAdminController::class);
        $mailLog = $container->get(MailLogAdminController::class);
        $account = $container->get(AccountController::class);

        return new RouteCollector([
            new Route('GET', '/health', $container->get(HealthController::class)),
            new Route('GET', '/account', [$account, 'dashboard']),
            new Route('POST', '/account/logout', [$account, 'logout']),
            new Route('POST', '/account/profile', [$account, 'updateProfile']),
            new Route('POST', '/account/password', [$account, 'updatePassword']),
            new Route('POST', '/account/2fa/start', [$account, 'startTotp']),
            new Route('POST', '/account/2fa/confirm', [$account, 'confirmTotp']),
            new Route('POST', '/account/2fa/disable', [$account, 'disableTotp']),
            new Route('POST', '/account/delete', [$account, 'deleteAccount']),
            new Route('GET', '/account/login', [$account, 'showLogin']),
            new Route('POST', '/account/login', [$account, 'login']),
            new Route('GET', '/account/login/2fa', [$account, 'showLoginTotp']),
            new Route('POST', '/account/login/2fa', [$account, 'submitLoginTotp']),
            new Route('GET', '/account/register', [$account, 'showRegister']),
            new Route('POST', '/account/register', [$account, 'register']),
            new Route('GET', '/account/password/forgot', [$account, 'showForgot']),
            new Route('POST', '/account/password/forgot', [$account, 'forgot']),
            new Route('GET', '/account/password/reset', [$account, 'showReset']),
            new Route('POST', '/account/password/reset', [$account, 'reset']),
            new Route('GET', '/admin/login', [$login, 'show']),
            new Route('POST', '/admin/login', [$login, 'submit']),
            new Route('GET', '/admin/login/2fa', [$login, 'showTotp']),
            new Route('POST', '/admin/login/2fa', [$login, 'submitTotp']),
            new Route('POST', '/admin/logout', [$login, 'logout']),
            new Route('GET', '/admin', $container->get(DashboardController::class)),
            new Route('GET', '/admin/ui-locale', [$container->get(UiLocaleAdminController::class), 'switch']),
            new Route('GET', '/admin/about', [$about, 'index']),
            new Route('POST', '/admin/about/check-update', [$about, 'checkUpdate']),
            new Route('GET', '/admin/about/license', [$about, 'license']),
            new Route('GET', '/admin/mail', [$mailLog, 'index']),
            new Route('GET', '/admin/mail/{id}', [$mailLog, 'show']),
            new Route('GET', '/admin/logs', [$container->get(LogsAdminController::class), 'index']),
            new Route('GET', '/admin/health', [$container->get(HealthAdminController::class), 'index']),
            new Route('GET', '/admin/pages/new', [$pages, 'createForm']),
            new Route('GET', '/admin/pages', [$pages, 'index']),
            new Route('POST', '/admin/pages', [$pages, 'create']),
            new Route('GET', '/admin/menus', [$menus, 'index']),
            new Route('POST', '/admin/menus', [$menus, 'save']),
            new Route('GET', '/admin/users', [$users, 'index']),
            new Route('POST', '/admin/users', [$users, 'create']),
            new Route('POST', '/admin/users/update', [$users, 'update']),
            new Route('POST', '/admin/users/delete', [$users, 'delete']),
            new Route('GET', '/admin/settings', [$settings, 'index']),
            new Route('POST', '/admin/settings', [$settings, 'save']),
            new Route('POST', '/admin/settings/locales', [$settings, 'addLocale']),
            new Route('POST', '/admin/settings/locales/update', [$settings, 'updateLocale']),
            new Route('POST', '/admin/settings/locales/default', [$settings, 'setDefaultLocale']),
            new Route('POST', '/admin/settings/locales/delete', [$settings, 'deleteLocale']),
            new Route('GET', '/admin/audit', [$audit, 'index']),
            new Route('GET', '/admin/media', [$media, 'index']),
            new Route('POST', '/admin/media', [$media, 'upload']),
            new Route('POST', '/admin/media/update', [$media, 'update']),
            new Route('POST', '/admin/media/variants/regenerate', [$media, 'regenerateVariants']),
            new Route('POST', '/admin/media/delete', [$media, 'delete']),
            new Route('POST', '/admin/media/folders', [$media, 'createFolder']),
            new Route('POST', '/admin/media/folders/delete', [$media, 'deleteFolder']),
            new Route('GET', '/admin/patterns', [$patterns, 'index']),
            new Route('POST', '/admin/patterns', [$patterns, 'create']),
            new Route('POST', '/admin/patterns/rename', [$patterns, 'rename']),
            new Route('POST', '/admin/patterns/delete', [$patterns, 'delete']),
            new Route('GET', '/admin/globals', [$globals, 'index']),
            new Route('POST', '/admin/globals', [$globals, 'save']),
            new Route('GET', '/admin/plugins', [$plugins, 'index']),
            new Route('GET', '/admin/plugins/marketplace', [$plugins, 'marketplace']),
            new Route('POST', '/admin/plugins/marketplace/install', [$plugins, 'installFromMarketplace']),
            new Route('POST', '/admin/plugins/upload', [$plugins, 'upload']),
            new Route('POST', '/admin/plugins/enable', [$plugins, 'enable']),
            new Route('POST', '/admin/plugins/disable', [$plugins, 'disable']),
            new Route('POST', '/admin/plugins/uninstall', [$plugins, 'uninstall']),
            new Route('GET', '/admin/plugins/settings', [$pluginSettings, 'edit']),
            new Route('POST', '/admin/plugins/settings', [$pluginSettings, 'save']),
            new Route('GET', '/admin/theme', [$theme, 'index']),
            new Route('POST', '/admin/theme/activate', [$theme, 'activate']),
            new Route('POST', '/admin/theme/branding', [$theme, 'saveBranding']),
            new Route('GET', '/admin/security', [$security, 'index']),
            new Route('POST', '/admin/security/2fa/start', [$security, 'startTotp']),
            new Route('POST', '/admin/security/2fa/confirm', [$security, 'confirmTotp']),
            new Route('POST', '/admin/security/2fa/disable', [$security, 'disableTotp']),
            new Route('GET', '/admin/webhooks', [$webhooks, 'index']),
            new Route('POST', '/admin/webhooks', [$webhooks, 'create']),
            new Route('POST', '/admin/webhooks/delete', [$webhooks, 'delete']),
            new Route('GET', '/admin/export', [$export, 'index']),
            new Route('POST', '/admin/export/download', [$export, 'download']),
            new Route('POST', '/admin/export/import', [$export, 'upload']),
            new Route('GET', '/admin/pages/{id}/builder', [$builder, 'edit']),
            new Route('POST', '/admin/pages/{id}/builder', [$builder, 'save']),
            new Route('POST', '/admin/pages/{id}/save-pattern', [$builder, 'savePattern']),
            new Route('POST', '/admin/pages/{id}/publish', [$builder, 'publish']),
            new Route('POST', '/admin/pages/{id}/submit-review', [$builder, 'submitReview']),
            new Route('POST', '/admin/pages/{id}/reject-review', [$builder, 'rejectReview']),
            new Route('POST', '/admin/pages/{id}/unpublish', [$builder, 'unpublish']),
            new Route('POST', '/admin/pages/{id}/schedule', [$builder, 'schedule']),
            new Route('POST', '/admin/pages/{id}/schedule-unpublish', [$builder, 'scheduleUnpublish']),
            new Route('POST', '/admin/pages/{id}/revert', [$builder, 'revert']),
            new Route('POST', '/admin/pages/{id}/editorial', [$builder, 'createEditorial']),
            new Route('POST', '/admin/pages/{id}/editorial/{itemId}/toggle', [$builder, 'toggleEditorial']),
            new Route('POST', '/admin/pages/{id}/editorial/{itemId}/delete', [$builder, 'deleteEditorial']),
            new Route('GET', '/admin/pages/{id}/preview', [$builder, 'preview']),
            new Route('GET', '/admin/pages/{id}', [$pages, 'editForm']),
            new Route('POST', '/admin/pages/{id}', [$pages, 'update']),
            new Route('POST', '/admin/pages/{id}/translations', [$pages, 'translate']),
            new Route('POST', '/admin/pages/{id}/delete', [$pages, 'delete']),
            new Route('GET', '/preview/{id}', $container->get(SignedPreviewController::class)),
            new Route('GET', '/media/{id}', $container->get(MediaServeController::class)),
            new Route('GET', '/media/{id}/{handle}', $container->get(MediaServeController::class)),
            new Route('GET', '/sitemap.xml', [$container->get(SitemapController::class), 'index']),
            new Route('GET', '/sitemap-{locale}.xml', [$container->get(SitemapController::class), 'locale']),
            new Route('GET', '/robots.txt', $container->get(RobotsTxtController::class)),
            new Route('GET', '/search', $container->get(SearchController::class)),
            new Route('GET', '/{locale:locale}/search', $container->get(SearchController::class)),
        ]);
    }),
    Router::class => factory(static function (ContainerInterface $container): Router {
        return new Router(
            $container->get(ResponseFactory::class),
            $container->get(RouteCollector::class),
            $container->get(PublicUi::class),
            $container->get(PublicPageController::class),
        );
    }),
    MiddlewarePipeline::class => factory(static function (ContainerInterface $container): MiddlewarePipeline {
        return new MiddlewarePipeline(
            [
                $container->get(ErrorHandlerMiddleware::class),
                $container->get(RequestIdMiddleware::class),
                $container->get(StripBasePathMiddleware::class),
                $container->get(SecurityHeadersMiddleware::class),
                $container->get(RateLimitMiddleware::class),
                $container->get(SessionMiddleware::class),
                $container->get(CsrfMiddleware::class),
                $container->get(ResolveSiteMiddleware::class),
                $container->get(MaintenanceMiddleware::class),
                $container->get(RequireAuthMiddleware::class),
                $container->get(PluginPreRouteMiddleware::class),
            ],
            $container->get(Router::class),
        );
    }),
    HttpKernel::class => autowire(),
];
