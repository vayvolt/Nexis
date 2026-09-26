<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Support\Uuid;
use Nexis\Tests\Support\BootsApplication;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class HeadRoutingTest extends TestCase
{
    use BootsApplication;

    public function testHeadRootUsesGetHandler(): void
    {
        $app = $this->bootApp();
        $request = new ServerRequest(
            'HEAD',
            'http://localhost/nexis/',
            [],
            null,
            '1.1',
            ['SCRIPT_NAME' => '/nexis/index.php'],
        );

        $response = $app->handle($request);

        self::assertContains($response->getStatusCode(), [302, 404]);
        self::assertTrue(Uuid::isValid($response->getHeaderLine('X-Request-Id')));
    }
}
