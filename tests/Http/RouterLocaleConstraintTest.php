<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\ResponseFactory;
use Nexis\Http\Route;
use Nexis\Http\RouteCollector;
use Nexis\Http\Router;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RouterLocaleConstraintTest extends TestCase
{
    public function testConstrainedLocaleDoesNotStealAdminPath(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $ui = new PublicUi(new Translator(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang'));
        $hit = '';
        $router = new Router($responses, new RouteCollector([
            new Route('GET', '/admin/blog', static function () use ($responses, &$hit) {
                $hit = 'admin';

                return $responses->html('admin');
            }),
            new Route('GET', '/{locale:locale}/blog', static function () use ($responses, &$hit) {
                $hit = 'archive';

                return $responses->html('archive');
            }),
        ]), $ui);

        $admin = $router->handle((new ServerRequest('GET', '/admin/blog'))->withAttribute('path', '/admin/blog'));
        self::assertSame(200, $admin->getStatusCode());
        self::assertSame('admin', $hit);

        $de = $router->handle((new ServerRequest('GET', '/de/blog'))->withAttribute('path', '/de/blog'));
        self::assertSame(200, $de->getStatusCode());
        self::assertSame('archive', $hit);
    }
}
