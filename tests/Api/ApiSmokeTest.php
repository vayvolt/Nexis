<?php

declare(strict_types=1);

namespace Nexis\Tests\Api;

use Nexis\Api\ApiError;
use Nexis\Support\Uuid;
use Nexis\Tests\Support\BootsApplication;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ApiSmokeTest extends TestCase
{
    use BootsApplication;

    public function testPublicSiteEndpointIsAnonymous(): void
    {
        $response = $this->get('/api/v1/public/sites/current');
        if ($response->getStatusCode() === 503) {
            self::markTestSkipped('No site installed.');
        }

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $payload = $this->decode($response);
        self::assertIsArray($payload['data'] ?? null);
        self::assertArrayHasKey('defaultLocale', $payload['data']);
        self::assertArrayHasKey('locales', $payload['data']);
        self::assertTrue(Uuid::isValid((string) $payload['requestId']));
    }

    public function testAdminApiRejectsAnonymousWithJsonEnvelope(): void
    {
        $response = $this->get('/api/v1/admin/site');
        if ($response->getStatusCode() === 503) {
            self::markTestSkipped('No site installed.');
        }

        self::assertSame(401, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('Bearer', $response->getHeaderLine('WWW-Authenticate'));
        $payload = $this->decode($response);
        self::assertSame(ApiError::UNAUTHORIZED, $payload['error']['code'] ?? null);
        self::assertNotSame('', (string) ($payload['error']['message'] ?? ''));
        self::assertTrue(Uuid::isValid((string) ($payload['requestId'] ?? '')));
    }

    public function testAdminApiRejectsUnknownBearerToken(): void
    {
        $response = $this->get('/api/v1/admin/pages', ['Authorization' => 'Bearer nx_' . str_repeat('0', 64)]);
        if ($response->getStatusCode() === 503) {
            self::markTestSkipped('No site installed.');
        }

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(ApiError::UNAUTHORIZED, $this->decode($response)['error']['code'] ?? null);
    }

    /**
     * @param array<string, string> $headers
     */
    private function get(string $path, array $headers = []): ResponseInterface
    {
        $app = $this->bootApp();

        return $app->handle(new ServerRequest(
            'GET',
            'http://localhost/nexis' . $path,
            $headers,
            null,
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
        self::assertIsArray($payload);

        return $payload;
    }
}
