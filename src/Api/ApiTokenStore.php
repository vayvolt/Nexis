<?php

declare(strict_types=1);

namespace Nexis\Api;

use DateTimeImmutable;
use Nexis\Auth\UserId;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;

/**
 * Personal access tokens for the Admin-API (`Authorization: Bearer nx_…`).
 * Only the SHA-256 hash is stored; the plaintext is returned once on create.
 */
final class ApiTokenStore
{
    public const PREFIX = 'nx_';

    /** Displayable head of the plaintext (`nx_` + 8 hex chars). */
    public const DISPLAY_PREFIX_LENGTH = 11;

    private const DATE_FORMAT = 'Y-m-d H:i:s.v';

    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    public static function generatePlaintext(): string
    {
        return self::PREFIX . bin2hex(random_bytes(32));
    }

    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public static function displayPrefix(string $plaintext): string
    {
        return substr($plaintext, 0, self::DISPLAY_PREFIX_LENGTH);
    }

    /**
     * @param list<string> $scopes Empty list grants everything the user may do at use time.
     */
    public function create(
        SiteId $siteId,
        UserId $userId,
        string $name,
        array $scopes = [],
        ?DateTimeImmutable $expiresAt = null,
    ): ApiTokenSecret {
        $plaintext = self::generatePlaintext();
        $normalizedScopes = self::normalizeScopes($scopes);
        $token = new ApiToken(
            Uuid::v7(),
            $siteId,
            $userId,
            $name,
            self::displayPrefix($plaintext),
            $normalizedScopes,
            null,
            $expiresAt,
            null,
            $this->clock->now(),
        );

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_tokens
                (id, site_id, user_id, name, token_prefix, token_hash, scopes_json, expires_at, created_at)
             VALUES
                (:id, :site_id, :user_id, :name, :token_prefix, :token_hash, :scopes_json, :expires_at, :created_at)',
        );
        $stmt->execute([
            'id' => $token->id,
            'site_id' => $siteId->value,
            'user_id' => $userId->value,
            'name' => $name,
            'token_prefix' => $token->tokenPrefix,
            'token_hash' => self::hash($plaintext),
            'scopes_json' => json_encode($normalizedScopes, JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt?->format(self::DATE_FORMAT),
            'created_at' => $token->createdAt->format(self::DATE_FORMAT),
        ]);

        return new ApiTokenSecret($token, $plaintext);
    }

    public function findByPlaintext(string $plaintext): ?ApiToken
    {
        if (!str_starts_with($plaintext, self::PREFIX)) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM api_tokens WHERE token_hash = :hash LIMIT 1');
            $stmt->execute(['hash' => self::hash($plaintext)]);
        } catch (\Throwable) {
            // Table may be missing before migrate; treat as "no token".
            return null;
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function find(string $id, SiteId $siteId): ?ApiToken
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM api_tokens WHERE id = :id AND site_id = :site_id LIMIT 1',
        );
        $stmt->execute(['id' => $id, 'site_id' => $siteId->value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @return list<ApiToken>
     */
    public function listForSite(SiteId $siteId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM api_tokens WHERE site_id = :site_id ORDER BY created_at DESC',
            );
            $stmt->execute(['site_id' => $siteId->value]);
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $out[] = $this->map($row);
            }
        }

        return $out;
    }

    public function revoke(string $id, SiteId $siteId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = :revoked_at
             WHERE id = :id AND site_id = :site_id AND revoked_at IS NULL',
        );
        $stmt->execute([
            'revoked_at' => $this->clock->now()->format(self::DATE_FORMAT),
            'id' => $id,
            'site_id' => $siteId->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function touchLastUsed(string $id): void
    {
        try {
            $stmt = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = :now WHERE id = :id');
            $stmt->execute([
                'now' => $this->clock->now()->format(self::DATE_FORMAT),
                'id' => $id,
            ]);
        } catch (\Throwable) {
            // Usage tracking must never fail an authenticated request.
        }
    }

    /**
     * @param list<string> $scopes
     * @return list<string>
     */
    public static function normalizeScopes(array $scopes): array
    {
        $out = [];
        foreach ($scopes as $scope) {
            if (!is_string($scope)) {
                continue;
            }
            $scope = trim($scope);
            if ($scope !== '' && !in_array($scope, $out, true)) {
                $out[] = $scope;
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): ApiToken
    {
        $scopes = [];
        $rawScopes = $row['scopes_json'] ?? null;
        if (is_string($rawScopes) && $rawScopes !== '') {
            $decoded = json_decode($rawScopes, true);
            if (is_array($decoded)) {
                $scopes = self::normalizeScopes(array_values($decoded));
            }
        }

        return new ApiToken(
            (string) $row['id'],
            new SiteId((string) $row['site_id']),
            new UserId((string) $row['user_id']),
            (string) $row['name'],
            (string) $row['token_prefix'],
            $scopes,
            self::parseDate($row['last_used_at'] ?? null),
            self::parseDate($row['expires_at'] ?? null),
            self::parseDate($row['revoked_at'] ?? null),
            self::parseDate($row['created_at'] ?? null) ?? $this->clock->now(),
        );
    }

    private static function parseDate(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $raw)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw);

        return $parsed instanceof DateTimeImmutable ? $parsed : null;
    }
}
