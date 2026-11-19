<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Cache\PageCacheBypassRegistry;
use Nexis\Event\EventDispatcher;
use Nexis\Event\UserAuthenticated;
use Nexis\Http\SitemapPathRegistry;
use Nexis\Site\SiteId;
use Nexis\Theme\TwigExtensionRegistry;
use Nexis\Webhook\WebhookEventRegistry;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class PageCacheBypassTest extends TestCase
{
    use PluginKernelTestFactory;

    public function testBypassWhenCheckerMatches(): void
    {
        $bypass = new PageCacheBypassRegistry();
        $kernel = $this->makePluginKernel(bypass: $bypass);
        $kernel->registerPageCacheBypass(static function (ServerRequestInterface $request): bool {
            return str_contains($request->getUri()->getPath(), '/cart');
        });

        self::assertTrue($kernel->shouldBypassPageCache(new ServerRequest('GET', 'http://example.com/de/cart')));
        self::assertFalse($kernel->shouldBypassPageCache(new ServerRequest('GET', 'http://example.com/de/about')));
        self::assertTrue($bypass->shouldBypass(new ServerRequest('GET', 'http://example.com/de/cart')));
    }

    public function testWebhookEventsMergeCoreAndPlugin(): void
    {
        $events = new WebhookEventRegistry();
        $kernel = $this->makePluginKernel(webhooks: $events);
        $kernel->registerWebhookEvent('shop.order.placed');
        self::assertContains('page.published', $kernel->webhookEvents());
        self::assertContains('shop.order.placed', $events->all());
    }

    public function testSitemapAndListenAndTwigHooks(): void
    {
        $sitemap = new SitemapPathRegistry();
        $dispatcher = new EventDispatcher();
        $twig = new TwigExtensionRegistry();
        $kernel = $this->makePluginKernel(sitemap: $sitemap, events: $dispatcher, twig: $twig);
        $kernel->forPlugin('acme/shop');
        $kernel->registerSitemapPath('/shop');
        $heard = false;
        $kernel->listen(UserAuthenticated::class, static function () use (&$heard): void {
            $heard = true;
        });
        $dispatcher->dispatch(new UserAuthenticated(
            new \Nexis\Auth\UserId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0700'),
            new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0701'),
        ));
        $kernel->registerTwigExtension(new class extends AbstractExtension {
            public function getFunctions(): array
            {
                return [new TwigFunction('cart_count', static fn (): int => 0)];
            }
        });

        self::assertSame(['/shop'], $sitemap->pathsFor(new SiteId('0193f0a0-7c2a-7e11-9c00-5f3c1a9b0701')));
        self::assertTrue($heard);
        self::assertCount(1, $twig->all());
    }
}
