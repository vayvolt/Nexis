<?php

declare(strict_types=1);

namespace Nexis\Tests\Api;

use Nexis\Api\ApiError;
use Nexis\Api\ApiTokenStore;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Auth\UserRepository;
use Nexis\Kernel\Application;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Tests\Support\BootsApplication;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use PDO;

/**
 * Multipart media upload through the admin API and token scopes.
 */
final class AdminMediaUploadApiTest extends TestCase
{
    use BootsApplication;

    private Application $app;
    private Site $site;
    private User $manager;
    private string $token;

    /** @var list<string> */
    private array $mediaIds = [];

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

        $manager = $this->memberWithMediaManage($site);
        if ($manager === null) {
            self::markTestSkipped('No member with content.media.manage.');
        }
        $this->manager = $manager;
        $this->token = $this->tokenWithScopes([Permission::CONTENT_MEDIA_MANAGE]);
    }

    protected function tearDown(): void
    {
        $pdo = $this->app->container->get(PDO::class);
        foreach ($this->mediaIds as $mediaId) {
            $pdo->prepare('DELETE FROM media_variants WHERE asset_id = ?')->execute([$mediaId]);
            $pdo->prepare('DELETE FROM media_assets WHERE id = ?')->execute([$mediaId]);
        }
        foreach ($this->tokenIds as $tokenId) {
            $pdo->prepare('DELETE FROM api_tokens WHERE id = ?')->execute([$tokenId]);
        }
        $this->mediaIds = [];
        $this->tokenIds = [];
    }

    public function testUploadRequiresFileField(): void
    {
        $response = $this->upload(null, ['alt_text' => 'x']);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(ApiError::INVALID_REQUEST, $this->decode($response)['error']['code'] ?? null);
    }

    public function testImageRequiresAltText(): void
    {
        $response = $this->upload($this->tinyPng(), []);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame(ApiError::INVALID_REQUEST, $this->decode($response)['error']['code'] ?? null);
    }

    public function testUploadReturnsCreatedMedia(): void
    {
        $response = $this->upload($this->tinyPng(), ['alt_text' => 'PHPUnit pixel']);
        self::assertSame(201, $response->getStatusCode(), (string) $response->getBody());
        $payload = $this->decode($response);
        $data = $payload['data'] ?? null;
        self::assertIsArray($data);
        self::assertSame('PHPUnit pixel', $data['altText'] ?? null);
        self::assertSame('image/png', $data['mime'] ?? null);
        $id = (string) ($data['id'] ?? '');
        self::assertNotSame('', $id);
        $this->mediaIds[] = $id;
        self::assertStringContainsString('/api/v1/admin/media/' . $id, $response->getHeaderLine('Location'));
    }

    public function testUploadForbiddenWithoutMediaScope(): void
    {
        $editOnly = $this->tokenWithScopes([Permission::CONTENT_PAGE_EDIT]);
        $response = $this->upload($this->tinyPng(), ['alt_text' => 'nope'], $editOnly);
        self::assertSame(403, $response->getStatusCode());
        self::assertSame(ApiError::FORBIDDEN, $this->decode($response)['error']['code'] ?? null);
    }

    private function tinyPng(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        self::assertNotFalse($png);

        return new UploadedFile(
            Stream::create($png),
            strlen($png),
            UPLOAD_ERR_OK,
            'pixel.png',
            'image/png',
        );
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(?UploadedFile $file, array $fields, ?string $token = null): ResponseInterface
    {
        $files = $file === null ? [] : ['file' => $file];
        $headers = ['Authorization' => 'Bearer ' . ($token ?? $this->token)];

        return $this->app->handle(
            (new ServerRequest(
                'POST',
                'http://localhost/nexis/api/v1/admin/media',
                $headers,
                null,
                '1.1',
                ['SCRIPT_NAME' => '/nexis/index.php'],
            ))->withUploadedFiles($files)->withParsedBody($fields),
        );
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
            $this->manager->id,
            'PHPUnit media API',
            $scopes,
        );
        $this->tokenIds[] = $created->token->id;

        return $created->plaintext;
    }

    private function memberWithMediaManage(Site $site): ?User
    {
        $policy = $this->app->container->get(SitePolicy::class);
        foreach ($this->app->container->get(UserRepository::class)->listMembers($site->id) as $member) {
            $user = $member['user'];
            if ($policy->can($user, $site, Permission::CONTENT_MEDIA_MANAGE)) {
                return $user;
            }
        }

        return null;
    }
}
