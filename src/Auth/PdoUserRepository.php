<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;
use PDO;

final class PdoUserRepository implements UserRepository, MembershipLookup
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function findById(UserId $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE id = :id AND deleted_at IS NULL LIMIT 1',
        );
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
        );
        $stmt->execute(['email' => mb_strtolower($email)]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id',
        );
        $stmt->execute([
            'last_login_at' => gmdate('Y-m-d H:i:s.v'),
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $user->id->value,
        ]);
    }

    public function updateTotpSecret(UserId $id, ?string $secret): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET totp_secret = :totp_secret, updated_at = :updated_at WHERE id = :id',
        );
        $stmt->execute([
            'totp_secret' => $secret,
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $id->value,
        ]);
    }

    public function listMembers(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*,
                    r.id AS role_id,
                    COALESCE(r.name, CASE WHEN u.is_platform_admin = 1 THEN \'Plattform-Admin\' ELSE \'—\' END) AS role_name,
                    COALESCE(r.slug, CASE WHEN u.is_platform_admin = 1 THEN \'platform\' ELSE \'\' END) AS role_slug
             FROM users u
             LEFT JOIN site_memberships m ON m.user_id = u.id AND m.site_id = :site_id
             LEFT JOIN roles r ON r.id = m.role_id
             WHERE u.deleted_at IS NULL
               AND (m.site_id IS NOT NULL OR u.is_platform_admin = 1)
             ORDER BY u.is_platform_admin DESC, u.display_name ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'user' => $this->map($row),
                'role_id' => is_string($row['role_id'] ?? null) ? (string) $row['role_id'] : '',
                'role_name' => (string) $row['role_name'],
                'role_slug' => (string) $row['role_slug'],
            ];
        }

        return $out;
    }

    public function listRoles(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug FROM roles
             WHERE site_id IS NULL OR site_id = :site_id
             ORDER BY name ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'slug' => (string) $row['slug'],
            ];
        }

        return $out;
    }

    public function createUser(
        string $email,
        string $passwordHash,
        string $displayName,
        bool $isPlatformAdmin,
        string $uiLocale,
    ): User {
        $id = \Nexis\Support\Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO users
                (id, email, password_hash, display_name, is_platform_admin, ui_locale, created_at, updated_at)
             VALUES
                (:id, :email, :password_hash, :display_name, :is_platform_admin, :ui_locale, :created_at, :updated_at)',
        );
        $stmt->execute([
            'id' => $id,
            'email' => mb_strtolower($email),
            'password_hash' => $passwordHash,
            'display_name' => $displayName,
            'is_platform_admin' => $isPlatformAdmin ? 1 : 0,
            'ui_locale' => $uiLocale,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new User(new UserId($id), mb_strtolower($email), $passwordHash, $displayName, $isPlatformAdmin, $uiLocale);
    }

    public function updateProfile(UserId $id, string $displayName, string $email, bool $isPlatformAdmin): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET display_name = :display_name, email = :email, is_platform_admin = :is_platform_admin, updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'display_name' => $displayName,
            'email' => mb_strtolower($email),
            'is_platform_admin' => $isPlatformAdmin ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $id->value,
        ]);
    }

    public function updateAccountProfile(UserId $id, string $displayName, string $email, string $uiLocale): void
    {
        $current = $this->findById($id);
        $email = mb_strtolower($email);
        $emailChanged = $current === null || $current->email !== $email;
        if ($emailChanged) {
            $stmt = $this->pdo->prepare(
                'UPDATE users SET display_name = :display_name, email = :email, ui_locale = :ui_locale,
                    email_verified_at = NULL, updated_at = :updated_at
                 WHERE id = :id AND deleted_at IS NULL',
            );
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE users SET display_name = :display_name, email = :email, ui_locale = :ui_locale,
                    updated_at = :updated_at
                 WHERE id = :id AND deleted_at IS NULL',
            );
        }
        $stmt->execute([
            'display_name' => $displayName,
            'email' => $email,
            'ui_locale' => $uiLocale,
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $id->value,
        ]);
    }

    public function updateUiLocale(UserId $id, string $uiLocale): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET ui_locale = :ui_locale, updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'ui_locale' => $uiLocale,
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $id->value,
        ]);
    }

    public function updatePassword(UserId $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'password_hash' => $passwordHash,
            'updated_at' => gmdate('Y-m-d H:i:s.v'),
            'id' => $id->value,
        ]);
    }

    public function softDelete(UserId $id): void
    {
        $now = gmdate('Y-m-d H:i:s.v');
        $anonEmail = 'deleted+' . str_replace('-', '', $id->value) . '@invalid.local';
        $stmt = $this->pdo->prepare(
            'UPDATE users SET
                email = :email,
                password_hash = :password_hash,
                display_name = :display_name,
                is_platform_admin = 0,
                totp_secret = NULL,
                deleted_at = :deleted_at,
                updated_at = :updated_at
             WHERE id = :id',
        );
        $stmt->execute([
            'email' => $anonEmail,
            'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID),
            'display_name' => 'Gelöschter Benutzer',
            'deleted_at' => $now,
            'updated_at' => $now,
            'id' => $id->value,
        ]);
        $this->pdo->prepare('DELETE FROM site_memberships WHERE user_id = :user_id')->execute(['user_id' => $id->value]);
        try {
            $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id')->execute(['user_id' => $id->value]);
        } catch (\Throwable) {
            // Table may not exist in older schemas.
        }
    }

    public function setMembership(SiteId $siteId, UserId $userId, string $roleId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM site_memberships WHERE site_id = :site_id AND user_id = :user_id LIMIT 1',
        );
        $stmt->execute(['site_id' => $siteId->value, 'user_id' => $userId->value]);
        $existing = $stmt->fetchColumn();
        if (is_string($existing) && $existing !== '') {
            $upd = $this->pdo->prepare(
                'UPDATE site_memberships SET role_id = :role_id WHERE id = :id',
            );
            $upd->execute(['role_id' => $roleId, 'id' => $existing]);

            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO site_memberships (id, site_id, user_id, role_id, created_at)
             VALUES (:id, :site_id, :user_id, :role_id, :created_at)',
        );
        $ins->execute([
            'id' => \Nexis\Support\Uuid::v7(),
            'site_id' => $siteId->value,
            'user_id' => $userId->value,
            'role_id' => $roleId,
            'created_at' => gmdate('Y-m-d H:i:s.v'),
        ]);
    }

    public function removeMembership(SiteId $siteId, UserId $userId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM site_memberships WHERE site_id = :site_id AND user_id = :user_id',
        );
        $stmt->execute(['site_id' => $siteId->value, 'user_id' => $userId->value]);
    }

    public function findRoleIdBySlug(SiteId $siteId, string $slug): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM roles
             WHERE slug = :slug AND (site_id = :site_id OR site_id IS NULL)
             ORDER BY site_id IS NULL ASC
             LIMIT 1',
        );
        $stmt->execute(['slug' => $slug, 'site_id' => $siteId->value]);
        $id = $stmt->fetchColumn();

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function ensureMemberRole(SiteId $siteId): string
    {
        $existing = $this->findRoleIdBySlug($siteId, RoleSlug::MEMBER);
        if ($existing !== null) {
            return $existing;
        }
        $id = \Nexis\Support\Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (id, site_id, name, slug, is_system, created_at, updated_at)
             VALUES (:id, :site_id, :name, :slug, 1, :created_at, :updated_at)',
        );
        $stmt->execute([
            'id' => $id,
            'site_id' => $siteId->value,
            'name' => 'Mitglied',
            'slug' => RoleSlug::MEMBER,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }

    public function markEmailVerified(UserId $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_verified_at = :verified_at, updated_at = :updated_at
             WHERE id = :id AND deleted_at IS NULL',
        );
        $now = gmdate('Y-m-d H:i:s.v');
        $stmt->execute([
            'verified_at' => $now,
            'updated_at' => $now,
            'id' => $id->value,
        ]);
    }

    public function roleSlugFor(UserId $userId, SiteId $siteId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.slug
             FROM site_memberships m
             INNER JOIN roles r ON r.id = m.role_id
             WHERE m.user_id = :user_id AND m.site_id = :site_id
             LIMIT 1',
        );
        $stmt->execute([
            'user_id' => $userId->value,
            'site_id' => $siteId->value,
        ]);
        $slug = $stmt->fetchColumn();

        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    public function countWithRoleSlug(SiteId $siteId, string $slug): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM site_memberships m
             INNER JOIN roles r ON r.id = m.role_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.site_id = :site_id
               AND r.slug = :slug
               AND u.deleted_at IS NULL',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'slug' => $slug,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function countPlatformAdmins(): int
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM users WHERE is_platform_admin = 1 AND deleted_at IS NULL',
        );
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function hasAccess(UserId $userId, SiteId $siteId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM site_memberships WHERE user_id = :user_id AND site_id = :site_id LIMIT 1',
        );
        $stmt->execute([
            'user_id' => $userId->value,
            'site_id' => $siteId->value,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function siteIdsFor(UserId $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT site_id FROM site_memberships WHERE user_id = :user_id',
        );
        $stmt->execute(['user_id' => $userId->value]);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            if (is_string($id)) {
                $ids[] = new SiteId($id);
            }
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): User
    {
        return new User(
            new UserId((string) $row['id']),
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) $row['display_name'],
            (int) $row['is_platform_admin'] === 1,
            (string) $row['ui_locale'],
            isset($row['totp_secret']) && is_string($row['totp_secret']) && $row['totp_secret'] !== ''
                ? $row['totp_secret']
                : null,
        );
    }
}
