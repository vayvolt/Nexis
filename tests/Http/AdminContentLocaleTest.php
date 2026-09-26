<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Auth\User;
use Nexis\Auth\UserId;
use Nexis\Http\AdminContentLocale;
use Nexis\Http\SessionStore;
use Nexis\Site\LocaleUrlStrategy;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteLocale;
use Nexis\Site\TenantId;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class AdminContentLocaleTest extends TestCase
{
    public function testQueryWinsAndIsRemembered(): void
    {
        $session = $this->memorySession();
        $service = new AdminContentLocale($session);
        $site = $this->site();

        $request = (new ServerRequest('GET', '/admin/pages'))
            ->withQueryParams(['locale' => 'en'])
            ->withAttribute('user', $this->user('de'));
        self::assertSame('en', $service->resolve($site, $request));
        self::assertSame('en', $session->get(AdminContentLocale::SESSION_KEY));
    }

    public function testUiLocaleWinsOverSessionWhenEnabled(): void
    {
        $session = $this->memorySession();
        $session->set(AdminContentLocale::SESSION_KEY, 'de');
        $service = new AdminContentLocale($session);
        $site = $this->site();

        $request = (new ServerRequest('GET', '/admin/pages'))
            ->withAttribute('user', $this->user('en'));
        self::assertSame('en', $service->resolve($site, $request));
        self::assertSame('en', $session->get(AdminContentLocale::SESSION_KEY));
        self::assertSame('en', $service->current($site, $this->user('en')));
    }

    public function testFallsBackToSessionThenDefaultWithoutUser(): void
    {
        $session = $this->memorySession();
        $session->set(AdminContentLocale::SESSION_KEY, 'en');
        $service = new AdminContentLocale($session);
        $site = $this->site();

        $request = new ServerRequest('GET', '/admin/pages');
        self::assertSame('en', $service->resolve($site, $request));

        $session->set(AdminContentLocale::SESSION_KEY, 'fr');
        self::assertSame('de', $service->resolve($site, $request));
    }

    public function testSwitchFromPageDetailGoesToList(): void
    {
        $service = new AdminContentLocale($this->memorySession());
        $site = $this->site();
        $target = $service->redirectAfterSwitch(
            $site,
            '/nexis',
            'en',
            '/nexis/admin/pages/0193f0a0-7c2a-7e11-9c00-5f3c1a9b0701?x=1',
        );

        self::assertSame('/nexis/admin/pages?locale=en', $target);
    }

    public function testSwitchFromWorkshopServicesGoesToServicesList(): void
    {
        $service = new AdminContentLocale($this->memorySession());
        $site = $this->site();
        $target = $service->redirectAfterSwitch(
            $site,
            '/nexis',
            'en',
            '/nexis/admin/workshop/services/0193f0a0-7c2a-7e11-9c00-5f3c1a9b0701',
        );

        self::assertSame('/nexis/admin/workshop/services?locale=en', $target);
    }

    /**
     * @return SessionStore
     */
    private function memorySession(): SessionStore
    {
        return new class implements SessionStore {
            /** @var array<string, mixed> */
            private array $data = [];

            public function start(string $cookiePath, bool $secure = false): void
            {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->data[$key] ?? $default;
            }

            public function set(string $key, mixed $value): void
            {
                $this->data[$key] = $value;
            }

            public function remove(string $key): void
            {
                unset($this->data[$key]);
            }

            public function regenerate(): void
            {
            }

            public function flash(string $key, mixed $value): void
            {
                $bag = $this->data['_flash'] ?? [];
                if (!is_array($bag)) {
                    $bag = [];
                }
                $bag[$key] = $value;
                $this->data['_flash'] = $bag;
            }

            public function pullFlash(string $key, mixed $default = null): mixed
            {
                $bag = $this->data['_flash'] ?? [];
                if (!is_array($bag) || !array_key_exists($key, $bag)) {
                    return $default;
                }
                $value = $bag[$key];
                unset($bag[$key]);
                if ($bag === []) {
                    unset($this->data['_flash']);
                } else {
                    $this->data['_flash'] = $bag;
                }

                return $value;
            }
        };
    }

    private function site(): Site
    {
        $id = new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0700');

        return new Site(
            $id,
            new TenantId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0701'),
            'Demo',
            'localhost',
            'de',
            LocaleUrlStrategy::Prefix,
            [
                new SiteLocale('de', 'Deutsch', 'de', 'de', true, true),
                new SiteLocale('en', 'English', 'en', 'en', false, true),
            ],
        );
    }

    private function user(string $uiLocale): User
    {
        return new User(
            new UserId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0702'),
            'admin@example.com',
            'hash',
            'Admin',
            true,
            $uiLocale,
        );
    }
}
