<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Builder\BlockRegistry;
use Nexis\Builder\BlockType;
use Nexis\Cache\PageCacheBypassRegistry;
use Nexis\Content\PrimaryNavLinkRegistry;
use Nexis\Auth\PolicyRegistry;
use Nexis\Event\EventDispatcher;
use Nexis\Http\CspContributor;
use Nexis\Http\CsrfExemptRegistry;
use Nexis\Http\Route;
use Nexis\Http\RouteCollector;
use Nexis\Http\SitemapPathRegistry;
use Nexis\Mail\MailJobHandler;
use Nexis\Mail\MailJobRegistry;
use Nexis\Queue\JobHandler;
use Nexis\Queue\JobHandlerRegistry;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Theme\TwigExtensionRegistry;
use Nexis\Webhook\WebhookEventRegistry;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Twig\Extension\ExtensionInterface;

final class PluginKernel
{
    /** @var list<AdminSlot> */
    private array $slots = [];

    /** @var list<callable(array<string, mixed>): (?array{label: string, url: string})> */
    private array $accountNavProviders = [];

    /**
     * Plugin-owned admin nav sections (active-state resolution).
     *
     * @var list<array{id: string, paths: list<string>, pageTypes: list<string>, plugin: string}>
     */
    private array $adminNavSections = [];

    /** @var list<PreRouteHandler> */
    private array $preRouteHandlers = [];

    /** @var list<CspContributor> */
    private array $cspContributors = [];

    /**
     * Pending permission grants from plugins (applied when syncPermissions is called).
     *
     * @var list<array{keys: list<string>, roles: list<string>}>
     */
    private array $permissionGrants = [];

    public function __construct(
        private BlockRegistry $blocks,
        private RouteCollector $routes,
        private LoggerInterface $logger,
        private MailJobRegistry $mailJobs,
        private JobHandlerRegistry $jobHandlers,
        private PageCacheBypassRegistry $pageCacheBypass,
        private WebhookEventRegistry $webhookEvents,
        private EventDispatcher $events,
        private SitemapPathRegistry $sitemapPaths,
        private PrimaryNavLinkRegistry $primaryNav,
        private TwigExtensionRegistry $twigExtensions,
        private SettingsSchemaRegistry $settingsSchema,
        private PdoSiteSettingsRepository $siteSettings,
        private PolicyRegistry $policies,
        private CsrfExemptRegistry $csrfExempt,
        private string $activePluginId = '',
    ) {
    }

    public function forPlugin(string $pluginId): self
    {
        $this->activePluginId = $pluginId;

        return $this;
    }

    public function registerBlock(BlockType $type): void
    {
        if ($this->blocks->has($type->type())) {
            $this->logger->warning('Plugin block skipped, type already registered', [
                'plugin' => $this->activePluginId,
                'type' => $type->type(),
            ]);

            return;
        }
        $this->blocks->register($type);
    }

    public function registerRoute(Route $route): void
    {
        $this->routes->add($route);
    }

    /**
     * @param callable(array<string, mixed>): string|callable(): string $renderer
     */
    public function registerAdminSlot(string $slotName, callable $renderer): void
    {
        $this->slots[] = new AdminSlot($slotName, $this->activePluginId, $renderer);
    }

    /**
     * Declare which admin URLs (and optional page types) belong to this plugin's nav item.
     *
     * @param array{
     *   paths?: list<string>,
     *   pageTypes?: list<string>
     * } $rules paths like "/admin/blog"; pageTypes like "post"
     */
    public function registerAdminNavSection(string $id, array $rules = []): void
    {
        $id = trim($id);
        if ($id === '') {
            return;
        }
        $paths = [];
        foreach ($rules['paths'] ?? [] as $path) {
            if (!is_string($path)) {
                continue;
            }
            $path = '/' . ltrim(trim($path), '/');
            if ($path !== '/') {
                $paths[] = rtrim($path, '/') ?: $path;
            }
        }
        $pageTypes = [];
        foreach ($rules['pageTypes'] ?? [] as $type) {
            if (is_string($type) && trim($type) !== '') {
                $pageTypes[] = trim($type);
            }
        }
        $this->adminNavSections[] = [
            'id' => $id,
            'paths' => array_values(array_unique($paths)),
            'pageTypes' => array_values(array_unique($pageTypes)),
            'plugin' => $this->activePluginId,
        ];
    }

    /**
     * Resolve the active admin-nav section id owned by a plugin, if any.
     */
    public function matchAdminNav(string $requestPath, string $basePath, ?\Nexis\Content\Page $page = null): ?string
    {
        $requestPath = rtrim($requestPath, '/') ?: '/';
        $basePath = rtrim($basePath, '/');

        if ($page !== null) {
            foreach ($this->adminNavSections as $section) {
                if (in_array($page->type, $section['pageTypes'], true)) {
                    return $section['id'];
                }
            }
        }

        foreach ($this->adminNavSections as $section) {
            foreach ($section['paths'] as $path) {
                $full = $basePath . $path;
                $full = rtrim($full, '/') ?: '/';
                if ($requestPath === $full || str_starts_with($requestPath, $full . '/')) {
                    return $section['id'];
                }
            }
        }

        return null;
    }

    /**
     * @param callable(array<string, mixed>): (?array{label: string, url: string}) $provider
     */
    public function registerAccountNavLink(callable $provider): void
    {
        $this->accountNavProviders[] = $provider;
    }

    /**
     * @param array<string, mixed> $ctx
     * @return list<array{label: string, url: string}>
     */
    public function accountNavLinks(array $ctx): array
    {
        $out = [];
        foreach ($this->accountNavProviders as $provider) {
            $link = $provider($ctx);
            if (!is_array($link)) {
                continue;
            }
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $out[] = ['label' => $label, 'url' => $url];
        }

        return $out;
    }

    public function registerPreRoute(PreRouteHandler $handler): void
    {
        $this->preRouteHandlers[] = $handler;
    }

    public function registerCspContributor(CspContributor $contributor): void
    {
        $this->cspContributors[] = $contributor;
    }

    /**
     * Skip CSRF for an /ext/ path prefix (payment webhooks). Verify HMAC yourself.
     */
    public function registerCsrfExempt(string $pathPrefix): void
    {
        $this->csrfExempt->register($pathPrefix);
    }

    /**
     * Register a mail job type handled via MailQueueWorker (queue `mail`).
     */
    public function registerMailJobHandler(string $type, MailJobHandler $handler): void
    {
        $this->mailJobs->register($type, $handler);
    }

    /**
     * Register a background worker for a dedicated job queue (processed by bin/queue-work.php).
     */
    public function registerJobHandler(string $queue, JobHandler $handler): void
    {
        $this->jobHandlers->register($queue, $handler);
    }

    /**
     * Skip full-page HTML cache when the checker returns true (e.g. cart cookie / shop path).
     *
     * @param callable(ServerRequestInterface): bool $checker
     */
    public function registerPageCacheBypass(callable $checker): void
    {
        $this->pageCacheBypass->register($checker);
    }

    public function shouldBypassPageCache(ServerRequestInterface $request): bool
    {
        return $this->pageCacheBypass->shouldBypass($request);
    }

    /**
     * Declare a webhook event name selectable in Admin (e.g. shop.order.placed).
     */
    public function registerWebhookEvent(string $eventName): void
    {
        $this->webhookEvents->register($eventName);
    }

    /**
     * Core + plugin-registered webhook event names.
     *
     * @return list<string>
     */
    public function webhookEvents(): array
    {
        return $this->webhookEvents->all();
    }

    /**
     * Listen to a core (or plugin) domain event.
     *
     * @param class-string $eventClass
     * @param callable(object): void $listener
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->events->listen($eventClass, $listener);
    }

    /**
     * Add a fixed public path to locale sitemaps (e.g. `/blog`, `/shop`).
     */
    public function registerSitemapPath(string $path): void
    {
        $this->sitemapPaths->registerPath($path);
    }

    /**
     * @param callable(\Nexis\Site\SiteId): list<string> $contributor
     */
    public function registerSitemapPaths(callable $contributor): void
    {
        $this->sitemapPaths->register($contributor);
    }

    /**
     * Keep a custom URL in the primary navigation while this plugin is enabled.
     *
     * @param callable(string $locale): string $labelForLocale
     */
    public function registerPrimaryNavLink(string $pagePath, callable $labelForLocale): void
    {
        $this->primaryNav->register($this->requireActivePlugin(), $pagePath, $labelForLocale);
    }

    /**
     * Register a Twig extension available in theme templates.
     */
    public function registerTwigExtension(ExtensionInterface $extension): void
    {
        $this->twigExtensions->register($extension);
    }

    /**
     * Declare typed site_settings fields for this plugin (stored as plugin:{id}.{field}).
     *
     * @param array<string, array{type?: string, default?: mixed, label?: string}|SettingsField> $fields
     */
    public function registerSettings(array $fields): void
    {
        $this->settingsSchema->register($this->requireActivePlugin(), $fields);
    }

    /**
     * Settings bag for the active plugin (or an explicit plugin id).
     */
    public function pluginSettings(?string $pluginId = null): PluginSettings
    {
        $id = $pluginId ?? $this->requireActivePlugin();

        return new PluginSettings($id, $this->settingsSchema, $this->siteSettings);
    }

    /**
     * Object-level authorization: $policy->allows($user, $site, 'ability', $subject).
     *
     * @param callable(\Nexis\Auth\User, \Nexis\Site\Site, mixed): bool $checker
     */
    public function registerPolicy(string $ability, callable $checker): void
    {
        $this->policies->register($ability, $checker);
    }

    /**
     * Register plugin permission keys and which site roles should receive them.
     *
     * @param list<string> $keys
     * @param list<string> $roles Role slugs (admin, editor, …). Empty = ensure keys only, no grants.
     */
    public function registerPermissions(array $keys, array $roles = ['admin', 'editor']): void
    {
        $normalized = [];
        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }
            $key = trim($key);
            if ($key !== '') {
                $normalized[] = $key;
            }
        }
        if ($normalized === []) {
            return;
        }
        $roleSlugs = [];
        foreach ($roles as $role) {
            if (!is_string($role)) {
                continue;
            }
            $role = trim($role);
            if ($role !== '') {
                $roleSlugs[] = $role;
            }
        }
        $this->permissionGrants[] = [
            'keys' => array_values(array_unique($normalized)),
            'roles' => array_values(array_unique($roleSlugs)),
        ];
    }

    /**
     * Persist registered plugin permissions into the DB and grant to roles.
     */
    public function syncPermissions(
        \Nexis\Auth\PdoPermissionLookup $permissions,
        \Nexis\Auth\UserRepository $users,
        \Nexis\Site\SiteId $siteId,
    ): void {
        foreach ($this->permissionGrants as $grant) {
            $permissions->ensurePermissions($grant['keys']);
            foreach ($grant['roles'] as $slug) {
                $roleId = $users->findRoleIdBySlug($siteId, $slug);
                if ($roleId !== null) {
                    $permissions->grantRole($roleId, $grant['keys']);
                }
            }
        }
    }

    /**
     * @return list<AdminSlot>
     */
    public function slots(string $name): array
    {
        return array_values(array_filter(
            $this->slots,
            static fn (AdminSlot $slot): bool => $slot->name === $name,
        ));
    }

    /**
     * @return list<PreRouteHandler>
     */
    public function preRouteHandlers(): array
    {
        return $this->preRouteHandlers;
    }

    /**
     * @return list<CspContributor>
     */
    public function cspContributors(): array
    {
        return $this->cspContributors;
    }

    public function activePluginId(): string
    {
        return $this->activePluginId;
    }

    public function requireActivePlugin(): string
    {
        if ($this->activePluginId === '') {
            throw new RuntimeException('Kein aktives Plugin im Kernel-Kontext.');
        }

        return $this->activePluginId;
    }
}
