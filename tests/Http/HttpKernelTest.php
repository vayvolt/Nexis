<?php

declare(strict_types=1);

namespace Nexis\Tests\Http;

use Nexis\Http\Controller\HealthController;
use Nexis\Http\HttpKernel;
use Nexis\Http\ResponseFactory;
use Nexis\Infrastructure\Database\DatabaseHealth;
use Nexis\Queue\JobQueue;
use Nexis\Support\SystemClock;
use Nexis\Support\Uuid;
use Nexis\Tests\Support\BootsApplication;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase;

final class HttpKernelTest extends TestCase
{
    use BootsApplication;

    public function testRootReturnsNoContentWithRequestId(): void
    {
        $app = $this->bootApp();
        $request = new ServerRequest(
            'GET',
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

    public function testUnknownRouteIsJson404(): void
    {
        $app = $this->bootApp();
        $request = new ServerRequest(
            'GET',
            'http://localhost/nexis/does-not-exist',
            [],
            null,
            '1.1',
            ['SCRIPT_NAME' => '/nexis/index.php'],
        );

        $response = $app->handle($request);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Nicht gefunden', (string) $response->getBody());
        self::assertTrue(Uuid::isValid($response->getHeaderLine('X-Request-Id')));
    }

    public function testHealthReportsDatabaseStatus(): void
    {
        $psr17 = new Psr17Factory();
        $responses = new ResponseFactory($psr17, $psr17);
        $health = new DatabaseHealth(static fn (): PDO => new PDO('sqlite::memory:'));
        $storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-health-' . bin2hex(random_bytes(4));
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT)');
        $jobs = new JobQueue($pdo, new SystemClock());
        $controller = new HealthController($responses, $health, $jobs, $storage);

        $response = $controller(new ServerRequest('GET', '/health'));
        $payload = json_decode((string) $response->getBody(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status']);
        self::assertSame('ok', $payload['database']);
        self::assertSame('ok', $payload['storage']);
        self::assertSame(0, $payload['queue']['pending'] ?? null);
    }

    public function testKernelIsRegistered(): void
    {
        $app = $this->bootApp();

        self::assertInstanceOf(HttpKernel::class, $app->container->get(HttpKernel::class));
    }
}
