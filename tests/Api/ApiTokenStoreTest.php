<?php

declare(strict_types=1);

namespace Nexis\Tests\Api;

use DateTimeImmutable;
use Nexis\Api\ApiTokenStore;
use Nexis\Auth\Permission;
use Nexis\Auth\UserId;
use Nexis\Infrastructure\Database\Migrator;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;
use PHPUnit\Framework\TestCase;

final class ApiTokenStoreTest extends TestCase
{
    private PDO $pdo;
    private ApiTokenStore $store;
    private SiteId $siteId;
    private UserId $userId;
    private string $schemaFile;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Migrator::ensureApiTokensTable() creates api_tokens for sqlite installs.
        $this->schemaFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis_api_' . bin2hex(random_bytes(4)) . '.sql';
        file_put_contents($this->schemaFile, "CREATE TABLE sites (id TEXT PRIMARY KEY);\n");
        (new Migrator($this->pdo, $this->schemaFile))->migrate();

        $this->store = new ApiTokenStore($this->pdo, self::clockAt('2026-09-28 10:00:00.000'));
        $this->siteId = new SiteId(Uuid::v7());
        $this->userId = new UserId(Uuid::v7());
    }

    protected function tearDown(): void
    {
        if (is_file($this->schemaFile)) {
            unlink($this->schemaFile);
        }
    }

    public function testCreateReturnsPlaintextOnceAndStoresOnlyTheHash(): void
    {
        $created = $this->store->create($this->siteId, $this->userId, 'CI Deploy');

        self::assertStringStartsWith(ApiTokenStore::PREFIX, $created->plaintext);
        self::assertSame(67, strlen($created->plaintext));
        self::assertSame(substr($created->plaintext, 0, 11), $created->token->tokenPrefix);

        $stmt = $this->pdo->query('SELECT token_hash, scopes_json FROM api_tokens');
        self::assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(hash('sha256', $created->plaintext), $row['token_hash']);
        self::assertStringNotContainsString($created->plaintext, (string) json_encode($row));
        self::assertSame('[]', $row['scopes_json']);
    }

    public function testFindByPlaintextVerifiesTheSecret(): void
    {
        $created = $this->store->create($this->siteId, $this->userId, 'Reader', [Permission::CONTENT_PAGE_EDIT]);

        $found = $this->store->findByPlaintext($created->plaintext);
        self::assertNotNull($found);
        self::assertSame($created->token->id, $found->id);
        self::assertSame([Permission::CONTENT_PAGE_EDIT], $found->scopes);
        self::assertTrue($found->isUsableAt(self::at('2026-09-28 10:05:00.000')));

        self::assertNull($this->store->findByPlaintext(ApiTokenStore::generatePlaintext()));
        self::assertNull($this->store->findByPlaintext('not-a-nexis-token'));
    }

    public function testScopesGateEffectivePermissions(): void
    {
        $scoped = $this->store->create($this->siteId, $this->userId, 'Scoped', [Permission::CONTENT_MEDIA_MANAGE])->token;
        self::assertTrue($scoped->allowsScope(Permission::CONTENT_MEDIA_MANAGE));
        self::assertFalse($scoped->allowsScope(Permission::SETTINGS_MANAGE));

        $unscoped = $this->store->create($this->siteId, $this->userId, 'Full')->token;
        self::assertTrue($unscoped->allowsScope(Permission::SETTINGS_MANAGE));
    }

    public function testRevokeMakesTokenUnusable(): void
    {
        $created = $this->store->create($this->siteId, $this->userId, 'Temporary');

        self::assertTrue($this->store->revoke($created->token->id, $this->siteId));
        self::assertFalse($this->store->revoke($created->token->id, $this->siteId));

        $revoked = $this->store->findByPlaintext($created->plaintext);
        self::assertNotNull($revoked);
        self::assertTrue($revoked->isRevoked());
        self::assertFalse($revoked->isUsableAt(self::at('2026-09-28 10:05:00.000')));
    }

    public function testExpiredTokenIsNotUsable(): void
    {
        $created = $this->store->create(
            $this->siteId,
            $this->userId,
            'Expiring',
            [],
            self::at('2026-09-28 11:00:00.000'),
        );

        $token = $this->store->findByPlaintext($created->plaintext);
        self::assertNotNull($token);
        self::assertTrue($token->isUsableAt(self::at('2026-09-28 10:30:00.000')));
        self::assertFalse($token->isUsableAt(self::at('2026-09-28 12:00:00.000')));
    }

    public function testTouchLastUsedAndListForSite(): void
    {
        $created = $this->store->create($this->siteId, $this->userId, 'Tracked');
        self::assertNull($created->token->lastUsedAt);

        $this->store->touchLastUsed($created->token->id);

        $tokens = $this->store->listForSite($this->siteId);
        self::assertCount(1, $tokens);
        self::assertNotNull($tokens[0]->lastUsedAt);
        self::assertSame([], $this->store->listForSite(new SiteId(Uuid::v7())));
    }

    public function testFindIsScopedToSite(): void
    {
        $created = $this->store->create($this->siteId, $this->userId, 'Scoped to site');

        self::assertNotNull($this->store->find($created->token->id, $this->siteId));
        self::assertNull($this->store->find($created->token->id, new SiteId(Uuid::v7())));
    }

    private static function clockAt(string $moment): Clock
    {
        return new class (self::at($moment)) implements Clock {
            public function __construct(private DateTimeImmutable $now)
            {
            }

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
    }

    /**
     * Default timezone on purpose: stored strings are parsed back without one,
     * so the round-trip must stay consistent (same as the page repositories).
     */
    private static function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment);
    }
}
