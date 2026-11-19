<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Support\Uuid;
use PDO;

final class PdoWebAuthnCredentialRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @return list<array{
     *   id: string,
     *   credential_id: string,
     *   public_key: string,
     *   sign_count: int,
     *   transports: ?string,
     *   name: string,
     *   created_at: string,
     *   last_used_at: ?string
     * }>
     */
    public function listForUser(UserId $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, credential_id, public_key, sign_count, transports, name, created_at, last_used_at
             FROM webauthn_credentials
             WHERE user_id = :user_id
             ORDER BY created_at DESC',
        );
        $stmt->execute(['user_id' => $userId->value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (string) $row['id'],
                'credential_id' => (string) $row['credential_id'],
                'public_key' => (string) $row['public_key'],
                'sign_count' => (int) $row['sign_count'],
                'transports' => isset($row['transports']) && is_string($row['transports']) ? $row['transports'] : null,
                'name' => (string) $row['name'],
                'created_at' => (string) $row['created_at'],
                'last_used_at' => isset($row['last_used_at']) && is_string($row['last_used_at']) ? $row['last_used_at'] : null,
            ];
        }

        return $out;
    }

    public function countForUser(UserId $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM webauthn_credentials WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId->value]);

        return (int) $stmt->fetchColumn();
    }

    public function save(
        UserId $userId,
        string $credentialId,
        string $publicKey,
        int $signCount,
        string $name,
        ?string $transports = null,
    ): string {
        $id = Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO webauthn_credentials
                (id, user_id, credential_id, public_key, sign_count, transports, name, created_at)
             VALUES
                (:id, :user_id, :credential_id, :public_key, :sign_count, :transports, :name, :created_at)',
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId->value,
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'sign_count' => $signCount,
            'transports' => $transports,
            'name' => $name,
            'created_at' => $now,
        ]);

        return $id;
    }

    /**
     * @return array{id: string, user_id: string, public_key: string, sign_count: int, name: string}|null
     */
    public function findByCredentialId(string $credentialId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, public_key, sign_count, name
             FROM webauthn_credentials
             WHERE credential_id = :credential_id
             LIMIT 1',
        );
        $stmt->execute(['credential_id' => $credentialId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'user_id' => (string) $row['user_id'],
            'public_key' => (string) $row['public_key'],
            'sign_count' => (int) $row['sign_count'],
            'name' => (string) $row['name'],
        ];
    }

    public function updateSignCount(string $id, int $signCount): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE webauthn_credentials
             SET sign_count = :sign_count, last_used_at = :last_used_at
             WHERE id = :id',
        );
        $stmt->execute([
            'sign_count' => $signCount,
            'last_used_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $id,
        ]);
    }

    public function deleteForUser(UserId $userId, string $credentialRowId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM webauthn_credentials WHERE id = :id AND user_id = :user_id',
        );
        $stmt->execute([
            'id' => $credentialRowId,
            'user_id' => $userId->value,
        ]);

        return $stmt->rowCount() > 0;
    }
}
