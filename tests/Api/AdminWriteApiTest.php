<?php

declare(strict_types=1);

namespace Nexis\Tests\Api;

use Nexis\Api\ApiError;
use Nexis\Api\ApiTokenStore;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Auth\UserRepository;
use Nexis\Builder\BlockDocument;
use Nexis\Cache\PageCache;
use Nexis\Kernel\Application;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use Nexis\Tests\Support\BootsApplication;
use Nexis\Theme\ThemeCatalog;
use Nexis\Theme\ThemeService;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Write endpoints end-to-end through routing, token auth and CSRF exemption.
 * The token is scoped to `content.page.edit`, so publishing must stay forbidden
 * even though the owning user may publish.
 */
final class AdminWriteApiTest extends TestCase
{
    use BootsApplication;

    private Application $app;
    private Site $site;
    private User $editor;
    private string $token;

    /** @var list<string> */
    private array $pageIds = [];

    /** @var list<string> */
    private array $tokenIds = [];

    protected function setUp(): void
    {
        $this->app = $this->bootApp();
        $site = $this->app->container->get(SiteRepository::class)->installed();
        if ($site === null) {
            self::markTestSkipped('No site installed.');
        }
        $this->site = $site;

        $editor = $this->memberWhoMayPublish($site);
        if ($editor === null) {
            self::markTestSkipped('No member with content.page.edit and content.page.publish.');
        }
        $this->editor = $editor;
        $this->token = $this->tokenWithScopes([Permission::CONTENT_PAGE_EDIT]);
    }

    protected function tearDown(): void
    {
        $pdo = $this->app->container->get(PDO::class);
        // Revisions, snapshots and editorial rows cascade with the page.
        foreach ($this->pageIds as $pageId) {
            $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$pageId]);
        }
        foreach ($this->tokenIds as $tokenId) {
            $pdo->prepare('DELETE FROM api_tokens WHERE id = ?')->execute([$tokenId]);
        }
        $this->pageIds = [];
        $this->tokenIds = [];
    }

    public function testCreateRejectsUnknownAndMalformedBodies(): void
    {
        $unknown = $this->send('POST', '/api/v1/admin/pages', ['titel' => 'Tippfehler']);
        self::assertSame(422, $unknown->getStatusCode());
        $payload = $this->decode($unknown);
        self::assertSame(ApiError::INVALID_REQUEST, $payload['error']['code'] ?? null);
        self::assertSame(['titel'], $payload['error']['details']['unknownFields'] ?? null);

        $malformed = $this->request('POST', '/api/v1/admin/pages', '{"title": ');
        self::assertSame(400, $malformed->getStatusCode());
        self::assertSame(ApiError::INVALID_JSON, $this->decode($malformed)['error']['code'] ?? null);

        $untitled = $this->send('POST', '/api/v1/admin/pages', ['title' => '  ']);
        self::assertSame(422, $untitled->getStatusCode());
    }

    public function testCreateThenPatchPageMetadata(): void
    {
        $page = $this->createPage('API Schreibtest');
        self::assertSame('draft', $page['status']);
        self::assertSame('post', $page['type']);
        self::assertSame($this->site->defaultSiteLocale()->locale, $page['locale']);

        $patched = $this->send('PATCH', '/api/v1/admin/pages/' . $page['id'], [
            'title' => 'API Schreibtest v2',
            'metaDescription' => 'Kurzbeschreibung',
            'robots' => 'noindex,follow',
        ]);
        self::assertSame(200, $patched->getStatusCode());
        $data = $this->decode($patched)['data'];
        self::assertSame('API Schreibtest v2', $data['title']);
        self::assertSame('api-schreibtest-v2', $data['slug']);
        self::assertSame('noindex,follow', $data['metadata']['robots']);
        self::assertSame('Kurzbeschreibung', $data['metadata']['metaDescription']);

        $cleared = $this->send('PATCH', '/api/v1/admin/pages/' . $page['id'], ['metaDescription' => null]);
        self::assertNull($this->decode($cleared)['data']['metadata']['metaDescription']);

        $badRobots = $this->send('PATCH', '/api/v1/admin/pages/' . $page['id'], ['robots' => 'nope']);
        self::assertSame(422, $badRobots->getStatusCode());
    }

    public function testDocumentPutEnforcesIfMatch(): void
    {
        $page = $this->createPage('API Dokument');
        $document = BlockDocument::empty()->toArray();

        $withoutHeader = $this->send('PUT', '/api/v1/admin/pages/' . $page['id'] . '/document', [
            'document' => $document,
        ]);
        self::assertSame(428, $withoutHeader->getStatusCode());
        self::assertSame('page.if_match_required', $this->decode($withoutHeader)['error']['code'] ?? null);

        $stale = $this->send(
            'PUT',
            '/api/v1/admin/pages/' . $page['id'] . '/document',
            ['document' => $document],
            ['If-Match' => 'deadbeef'],
        );
        self::assertSame(409, $stale->getStatusCode());
        $conflict = $this->decode($stale);
        self::assertSame('page.conflict', $conflict['error']['code'] ?? null);

        $current = $this->send('GET', '/api/v1/admin/pages/' . $page['id'] . '/document');
        self::assertSame(200, $current->getStatusCode());
        $hash = $this->decode($current)['data']['documentHash'];
        self::assertSame($hash, $conflict['error']['details']['documentHash'] ?? null);

        $saved = $this->send(
            'PUT',
            '/api/v1/admin/pages/' . $page['id'] . '/document',
            ['document' => $document, 'message' => 'Per API gespeichert'],
            ['If-Match' => '"' . $hash . '"'],
        );
        self::assertSame(200, $saved->getStatusCode());
        $revision = $this->decode($saved)['data'];
        self::assertSame('Per API gespeichert', $revision['message']);
        self::assertArrayNotHasKey('document', $revision);

        $stored = $this->decode($this->send('GET', '/api/v1/admin/pages/' . $page['id'] . '/document'))['data'];
        self::assertSame($revision['revisionId'], $stored['revisionId']);
        self::assertSame($revision['documentHash'], $stored['documentHash']);

        // The same hash is stale now that a newer revision exists.
        $replay = $this->send(
            'PUT',
            '/api/v1/admin/pages/' . $page['id'] . '/document',
            ['document' => $document],
            ['If-Match' => $hash],
        );
        self::assertSame(409, $replay->getStatusCode());
    }

    public function testDocumentPutRejectsMissingDocument(): void
    {
        $page = $this->createPage('API ohne Dokument');

        $response = $this->send(
            'PUT',
            '/api/v1/admin/pages/' . $page['id'] . '/document',
            ['message' => 'kein Dokument'],
            ['If-Match' => '*'],
        );
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(ApiError::INVALID_REQUEST, $this->decode($response)['error']['code'] ?? null);
    }

    public function testTranslationsCreateIsIdempotent(): void
    {
        $target = $this->secondLocale();
        if ($target === null) {
            self::markTestSkipped('Site has only one enabled locale.');
        }
        $page = $this->createPage('API Übersetzung');

        $created = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/translations', [
            'locale' => $target,
        ]);
        self::assertSame(201, $created->getStatusCode(), (string) $created->getBody());
        $translation = $this->decode($created)['data'];
        $this->pageIds[] = (string) $translation['id'];
        self::assertNotSame($page['id'], $translation['id']);
        self::assertSame($target, $translation['locale']);
        self::assertSame($page['translationGroupId'], $translation['translationGroupId']);
        self::assertSame('draft', $translation['status']);
        self::assertStringEndsWith(
            '/api/v1/admin/pages/' . $translation['id'],
            $created->getHeaderLine('Location'),
        );

        // Second call must not create a sibling, it returns the existing one.
        $again = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/translations', [
            'locale' => $target,
            'copyBlocks' => false,
        ]);
        self::assertSame(200, $again->getStatusCode(), (string) $again->getBody());
        self::assertSame($translation['id'], $this->decode($again)['data']['id']);

        $alternates = $this->send('GET', '/api/v1/admin/pages/' . $page['id'] . '/alternates');
        self::assertSame(200, $alternates->getStatusCode());
        $locales = array_column($this->decode($alternates)['data'], 'locale');
        self::assertContains($page['locale'], $locales);
        self::assertContains($target, $locales);

        $unknownLocale = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/translations', [
            'locale' => 'zz',
        ]);
        self::assertSame(422, $unknownLocale->getStatusCode());

        $wrongType = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/translations', [
            'locale' => $target,
            'copyBlocks' => 'ja',
        ]);
        self::assertSame(422, $wrongType->getStatusCode());
        self::assertSame(
            ['copyBlocks'],
            $this->decode($wrongType)['error']['details']['invalidFields'] ?? null,
        );
    }

    public function testRevertNeedsARevisionAndWritesANewDraft(): void
    {
        $this->token = $this->tokenWithScopes([
            Permission::CONTENT_PAGE_EDIT,
            Permission::CONTENT_PAGE_PUBLISH,
        ]);
        $page = $this->createPage('API Revert');
        $initial = $this->decode($this->send('GET', '/api/v1/admin/pages/' . $page['id'] . '/document'))['data'];

        $missing = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/revert', []);
        self::assertSame(422, $missing->getStatusCode());
        self::assertSame(ApiError::INVALID_REQUEST, $this->decode($missing)['error']['code'] ?? null);

        $unknown = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/revert', [
            'revisionId' => Uuid::v7(),
        ]);
        self::assertSame(404, $unknown->getStatusCode());
        self::assertSame('revision.not_found', $this->decode($unknown)['error']['code'] ?? null);

        $saved = $this->send(
            'PUT',
            '/api/v1/admin/pages/' . $page['id'] . '/document',
            ['document' => BlockDocument::empty()->toArray()],
            ['If-Match' => $initial['documentHash']],
        );
        self::assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());

        $reverted = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/revert', [
            'revisionId' => $initial['revisionId'],
        ]);
        self::assertSame(200, $reverted->getStatusCode(), (string) $reverted->getBody());
        $revision = $this->decode($reverted)['data'];
        self::assertNotSame($initial['revisionId'], $revision['revisionId']);
        self::assertSame($initial['documentHash'], $revision['documentHash']);
        self::assertSame($initial['document'], $revision['document']);

        $latest = $this->decode($this->send('GET', '/api/v1/admin/pages/' . $page['id'] . '/document'))['data'];
        self::assertSame($revision['revisionId'], $latest['revisionId']);
    }

    public function testBlocksCatalogIsReadableWithAPageEditToken(): void
    {
        $response = $this->send('GET', '/api/v1/admin/blocks');

        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $blocks = $this->decode($response)['data'];
        self::assertNotSame([], $blocks);
        self::assertContains('core/section', array_column($blocks, 'type'));
        foreach ($blocks as $block) {
            self::assertArrayHasKey('label', $block);
            self::assertArrayHasKey('allowsChildren', $block);
            self::assertArrayHasKey('propsSchema', $block);
            self::assertArrayHasKey('defaultProps', $block);
        }
    }

    public function testPluginEndpointsNeedThePluginManageScope(): void
    {
        $forbidden = [
            ['GET', '/api/v1/admin/plugins'],
            ['POST', '/api/v1/admin/plugins/nexis/forms/enable'],
            ['POST', '/api/v1/admin/plugins/nexis/forms/disable'],
        ];
        foreach ($forbidden as [$method, $path]) {
            $response = $this->send($method, $path, $method === 'GET' ? null : []);
            self::assertSame(403, $response->getStatusCode(), $path);
            self::assertSame(
                [Permission::PLUGIN_MANAGE],
                $this->decode($response)['error']['details']['requiredPermissions'] ?? null,
                $path,
            );
        }
    }

    public function testPluginsIndexListsTheCatalog(): void
    {
        if (!$this->app->container->get(SitePolicy::class)->can($this->editor, $this->site, Permission::PLUGIN_MANAGE)) {
            self::markTestSkipped('Member may not manage plugins.');
        }
        $this->token = $this->tokenWithScopes([Permission::PLUGIN_MANAGE]);

        $response = $this->send('GET', '/api/v1/admin/plugins');
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $plugins = $this->decode($response)['data'];
        self::assertNotSame([], $plugins);
        foreach ($plugins as $plugin) {
            self::assertSame($plugin['id'], $plugin['key']);
            self::assertStringContainsString('/', $plugin['key']);
            self::assertArrayHasKey('blocks', $plugin['available']);
            self::assertArrayHasKey('permissions', $plugin['available']);
            self::assertArrayHasKey('slots', $plugin['available']);
        }

        $unknown = $this->send('POST', '/api/v1/admin/plugins/acme/nope/enable', []);
        self::assertSame(404, $unknown->getStatusCode());
        self::assertSame('plugin.not_found', $this->decode($unknown)['error']['code'] ?? null);
    }

    public function testThemeTokensNeedTheThemeManageScope(): void
    {
        foreach ([['GET', null], ['PATCH', ['tokens' => ['color.text' => '#000000']]]] as [$method, $body]) {
            $response = $this->send($method, '/api/v1/admin/theme/tokens', $body);
            self::assertSame(403, $response->getStatusCode(), $method);
            self::assertSame(
                [Permission::THEME_MANAGE],
                $this->decode($response)['error']['details']['requiredPermissions'] ?? null,
            );
        }
    }

    public function testThemeTokensPatchAcceptsKnownTokensOnly(): void
    {
        if (!$this->app->container->get(SitePolicy::class)->can($this->editor, $this->site, Permission::THEME_MANAGE)) {
            self::markTestSkipped('Member may not manage the theme.');
        }
        $this->token = $this->tokenWithScopes([Permission::THEME_MANAGE]);
        $catalog = $this->app->container->get(ThemeCatalog::class);
        $before = $catalog->overrides($this->site->id);
        $customCss = $catalog->customCss($this->site->id);

        try {
            $unknown = $this->send('PATCH', '/api/v1/admin/theme/tokens', [
                'tokens' => ['color.bogus' => '#ffffff'],
            ]);
            self::assertSame(422, $unknown->getStatusCode());
            self::assertSame(
                ['color.bogus'],
                $this->decode($unknown)['error']['details']['unknownTokens'] ?? null,
            );

            $badValue = $this->send('PATCH', '/api/v1/admin/theme/tokens', [
                'tokens' => ['layout.pageTitle' => 'maybe'],
            ]);
            self::assertSame(422, $badValue->getStatusCode());
            self::assertSame('layout.pageTitle', $this->decode($badValue)['error']['details']['token'] ?? null);

            $default = $this->app->container->get(ThemeService::class)->defaultsFor($this->site)['layout.pageTitle'] ?? '';
            $changed = $default === 'show' ? 'hide' : 'show';
            $saved = $this->send('PATCH', '/api/v1/admin/theme/tokens', [
                'tokens' => ['layout.pageTitle' => $changed],
            ]);
            self::assertSame(200, $saved->getStatusCode(), (string) $saved->getBody());
            self::assertSame($changed, $this->decode($saved)['data']['tokens']['layout.pageTitle'] ?? null);
            self::assertSame($changed, $catalog->overrides($this->site->id)['layout.pageTitle'] ?? null);

            $read = $this->send('GET', '/api/v1/admin/theme/tokens');
            self::assertSame(200, $read->getStatusCode());
            self::assertSame($changed, $this->decode($read)['data']['tokens']['layout.pageTitle'] ?? null);

            // The theme default is never stored as an override.
            $asDefault = $this->send('PATCH', '/api/v1/admin/theme/tokens', [
                'tokens' => ['layout.pageTitle' => $default],
            ]);
            self::assertSame(200, $asDefault->getStatusCode());
            self::assertArrayNotHasKey('layout.pageTitle', $catalog->overrides($this->site->id));

            $saved = $this->send('PATCH', '/api/v1/admin/theme/tokens', [
                'tokens' => ['layout.pageTitle' => $changed],
            ]);
            self::assertSame(200, $saved->getStatusCode());

            // null drops the override again.
            $cleared = $this->send('PATCH', '/api/v1/admin/theme/tokens', [
                'tokens' => ['layout.pageTitle' => null],
            ]);
            self::assertSame(200, $cleared->getStatusCode());
            self::assertArrayNotHasKey('layout.pageTitle', (array) $this->decode($cleared)['data']['tokens']);
            self::assertArrayNotHasKey('layout.pageTitle', $catalog->overrides($this->site->id));
        } finally {
            $catalog->saveOverrides(
                $this->site->id,
                $before,
                $customCss !== '' ? $customCss : null,
                $this->editor->id->value,
            );
            $this->app->container->get(PageCache::class)->invalidateSite($this->site->id);
        }
    }

    public function testPublishUnpublishAndRevertAreDeniedWithoutPublishScope(): void
    {
        $page = $this->createPage('API Publish-Scope');

        foreach (['publish', 'unpublish', 'revert'] as $action) {
            $response = $this->send('POST', '/api/v1/admin/pages/' . $page['id'] . '/' . $action, []);
            self::assertSame(403, $response->getStatusCode(), $action);
            $payload = $this->decode($response);
            self::assertSame(ApiError::FORBIDDEN, $payload['error']['code'] ?? null);
            self::assertSame(
                [Permission::CONTENT_PAGE_PUBLISH],
                $payload['error']['details']['requiredPermissions'] ?? null,
            );
        }
    }

    public function testSiteUpdateNeedsSettingsManage(): void
    {
        $response = $this->send('PATCH', '/api/v1/admin/site', ['name' => 'Umbenannt']);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(
            [Permission::SETTINGS_MANAGE],
            $this->decode($response)['error']['details']['requiredPermissions'] ?? null,
        );
        self::assertSame($this->site->name, $this->app->container->get(SiteRepository::class)->installed()?->name);
    }

    public function testSiteUpdateAcceptsTheAllowedBasicsOnly(): void
    {
        if (!$this->app->container->get(SitePolicy::class)->can($this->editor, $this->site, Permission::SETTINGS_MANAGE)) {
            self::markTestSkipped('Member may not manage settings.');
        }
        $this->token = $this->tokenWithScopes([Permission::SETTINGS_MANAGE]);

        // Writes the values the site already has, so the dev site stays as it is.
        $response = $this->send('PATCH', '/api/v1/admin/site', ['name' => $this->site->name]);
        self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $data = $this->decode($response)['data'];
        self::assertSame($this->site->name, $data['name']);
        self::assertSame($this->site->defaultLocale, $data['defaultLocale']);

        $badStrategy = $this->send('PATCH', '/api/v1/admin/site', ['localeUrlStrategy' => 'carrier-pigeon']);
        self::assertSame(422, $badStrategy->getStatusCode());

        $blankName = $this->send('PATCH', '/api/v1/admin/site', ['name' => '']);
        self::assertSame(422, $blankName->getStatusCode());
        self::assertSame($this->site->name, $this->app->container->get(SiteRepository::class)->installed()?->name);
    }

    public function testUnknownPageIdIsNotFound(): void
    {
        $response = $this->send('PATCH', '/api/v1/admin/pages/not-a-uuid', ['title' => 'X']);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('page.not_found', $this->decode($response)['error']['code'] ?? null);
    }

    /**
     * Type `post` on purpose: page types would also land in the primary menu.
     *
     * @return array<string, mixed>
     */
    private function createPage(string $title): array
    {
        $response = $this->send('POST', '/api/v1/admin/pages', [
            'title' => $title,
            'type' => 'post',
        ]);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $page = $this->decode($response)['data'];
        self::assertIsArray($page);
        $this->pageIds[] = (string) $page['id'];

        return $page;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function send(string $method, string $path, ?array $body = null, array $headers = []): ResponseInterface
    {
        $encoded = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR);

        return $this->request($method, $path, $encoded, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, string $path, ?string $body, array $headers = []): ResponseInterface
    {
        $headers['Authorization'] = 'Bearer ' . $this->token;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return $this->app->handle(new ServerRequest(
            $method,
            'http://localhost/nexis' . $path,
            $headers,
            $body,
            '1.1',
            ['SCRIPT_NAME' => '/nexis/index.php'],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload, (string) $response->getBody());

        return $payload;
    }

    /**
     * @param list<string> $scopes
     */
    private function tokenWithScopes(array $scopes): string
    {
        $created = $this->app->container->get(ApiTokenStore::class)->create(
            $this->site->id,
            $this->editor->id,
            'PHPUnit write API',
            $scopes,
        );
        $this->tokenIds[] = $created->token->id;

        return $created->plaintext;
    }

    /**
     * First enabled locale that is not the default one, for the translation flow.
     */
    private function secondLocale(): ?string
    {
        $default = $this->site->defaultSiteLocale()->locale;
        foreach ($this->site->enabledLocales() as $locale) {
            if ($locale->locale !== $default) {
                return $locale->locale;
            }
        }

        return null;
    }

    private function memberWhoMayPublish(Site $site): ?User
    {
        $policy = $this->app->container->get(SitePolicy::class);
        foreach ($this->app->container->get(UserRepository::class)->listMembers($site->id) as $member) {
            $user = $member['user'];
            if ($policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)
                && $policy->can($user, $site, Permission::CONTENT_PAGE_PUBLISH)
            ) {
                return $user;
            }
        }

        return null;
    }
}
