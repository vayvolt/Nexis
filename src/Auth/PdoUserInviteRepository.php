<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;

final class PdoUserInviteRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @return array{id: string, raw: string}
     */
    public function create(
        SiteId $siteId,
        string $email,
        string $displayName,
        string $roleId,
        bool $isPlatformAdmin,
        UserId $invitedBy,
        int $ttlSeconds = 604800,
    ): array {
        $raw = bin2hex(random_bytes(32));
        $id = Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $expires = gmdate('Y-m-d H:i:s.v', time() + max(3600, $ttlSeconds));
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_invites (
                id, site_id, email, display_name, role_id, is_platform_admin,
                token_hash, invited_by, expires_at, created_at
             ) VALUES (
                :id, :site_id, :email, :display_name, :role_id, :is_platform_admin,
                :token_hash, :invited_by, :expires_at, :created_at
             )',
        );
        $stmt->execute([
            'id' => $id,
            'site_id' => $siteId->value,
            'email' => mb_strtolower(trim($email)),
            'display_name' => trim($displayName),
            'role_id' => $roleId,
            'is_platform_admin' => $isPlatformAdmin ? 1 : 0,
            'token_hash' => hash('sha256', $raw),
            'invited_by' => $invitedBy->value,
            'expires_at' => $expires,
            'created_at' => $now,
        ]);

        return ['id' => $id, 'raw' => $raw];
    }

    public function invalidateOpenForEmail(SiteId $siteId, string $email): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_invites
             SET accepted_at = :accepted_at
             WHERE site_id = :site_id
               AND email = :email
               AND accepted_at IS NULL',
        );
        $stmt->execute([
            'accepted_at' => gmdate('Y-m-d H:i:s.v'),
            'site_id' => $siteId->value,
            'email' => mb_strtolower(trim($email)),
        ]);
    }

    /**
     * @return array{
     *   id: string,
     *   site_id: string,
     *   email: string,
     *   display_name: string,
     *   role_id: string,
     *   is_platform_admin: bool
     * }|null
     */
    public function findValid(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $stmt = $this->pdo->prepare(
            'SELECT id, site_id, email, display_name, role_id, is_platform_admin
             FROM user_invites
             WHERE token_hash = :token_hash
               AND accepted_at IS NULL
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
            'site_id' => (string) $row['site_id'],
            'email' => (string) $row['email'],
            'display_name' => (string) $row['display_name'],
            'role_id' => (string) $row['role_id'],
            'is_platform_admin' => (int) ($row['is_platform_admin'] ?? 0) === 1,
        ];
    }

    public function markAccepted(string $inviteId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_invites SET accepted_at = :accepted_at WHERE id = :id',
        );
        $stmt->execute([
            'accepted_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $inviteId,
        ]);
    }

    /**
     * Invalidate an open invite for this site (link stops working).
     *
     * @return array{id: string, email: string, display_name: string}|null
     */
    public function revokeOpen(SiteId $siteId, string $inviteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name
             FROM user_invites
             WHERE id = :id
               AND site_id = :site_id
               AND accepted_at IS NULL
               AND expires_at > :now
             LIMIT 1',
        );
        $stmt->execute([
            'id' => $inviteId,
            'site_id' => $siteId->value,
            'now' => gmdate('Y-m-d H:i:s.v'),
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $this->markAccepted((string) $row['id']);

        return [
            'id' => (string) $row['id'],
            'email' => (string) $row['email'],
            'display_name' => (string) $row['display_name'],
        ];
    }

    /**
     * @return list<array{id: string, email: string, display_name: string, expires_at: string, created_at: string}>
     */
    public function listOpen(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, display_name, expires_at, created_at
             FROM user_invites
             WHERE site_id = :site_id
               AND accepted_at IS NULL
               AND expires_at > :now
             ORDER BY created_at DESC',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'now' => gmdate('Y-m-d H:i:s.v'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (string) $row['id'],
                'email' => (string) $row['email'],
                'display_name' => (string) $row['display_name'],
                'expires_at' => (string) $row['expires_at'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $out;
    }
}
