<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;

final class PdoPasswordResetRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @return array{id: string, raw: string}
     */
    public function create(UserId $userId, int $ttlSeconds = 3600): array
    {
        $raw = bin2hex(random_bytes(32));
        $id = Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $expires = gmdate('Y-m-d H:i:s.v', time() + max(300, $ttlSeconds));
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (id, user_id, token_hash, expires_at, created_at)
             VALUES (:id, :user_id, :token_hash, :expires_at, :created_at)',
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId->value,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => $expires,
            'created_at' => $now,
        ]);

        return ['id' => $id, 'raw' => $raw];
    }

    public function invalidateOpenForUser(UserId $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_reset_tokens
             SET used_at = :used_at
             WHERE user_id = :user_id AND used_at IS NULL',
        );
        $stmt->execute([
            'used_at' => gmdate('Y-m-d H:i:s.v'),
            'user_id' => $userId->value,
        ]);
    }

    /**
     * @return array{id: string, user_id: string}|null
     */
    public function findValid(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id FROM password_reset_tokens
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at > :now
             LIMIT 1',
        );
        $stmt->execute([
            'token_hash' => $hash,
            'now' => gmdate('Y-m-d H:i:s.v'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'user_id' => (string) $row['user_id'],
        ];
    }

    public function markUsed(string $tokenId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = :used_at WHERE id = :id',
        );
        $stmt->execute([
            'used_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $tokenId,
        ]);
    }
}
