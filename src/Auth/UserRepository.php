<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\SiteId;

interface UserRepository
{
    public function findById(UserId $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    public function updateTotpSecret(UserId $id, ?string $secret): void;

    /**
     * @return list<array{user: User, role_id: string, role_name: string, role_slug: string}>
     */
    public function listMembers(SiteId $siteId): array;

    /**
     * @return list<array{id: string, name: string, slug: string}>
     */
    public function listRoles(SiteId $siteId): array;

    public function createUser(
        string $email,
        string $passwordHash,
        string $displayName,
        bool $isPlatformAdmin,
        string $uiLocale,
    ): User;

    public function updateProfile(UserId $id, string $displayName, string $email, bool $isPlatformAdmin): void;

    public function updateAccountProfile(UserId $id, string $displayName, string $email, string $uiLocale): void;

    public function updateUiLocale(UserId $id, string $uiLocale): void;

    public function updatePassword(UserId $id, string $passwordHash): void;

    public function softDelete(UserId $id): void;

    public function setMembership(SiteId $siteId, UserId $userId, string $roleId): void;

    public function removeMembership(SiteId $siteId, UserId $userId): void;

    public function findRoleIdBySlug(SiteId $siteId, string $slug): ?string;

    /**
     * Ensures the member system role exists for the site. Prefer SystemRoleSeeder
     * for full role templates (admin, Redakteur, SEO, Mitglied).
     */
    public function ensureMemberRole(SiteId $siteId): string;

    public function markEmailVerified(UserId $id): void;

    public function roleSlugFor(UserId $userId, SiteId $siteId): ?string;

    public function countWithRoleSlug(SiteId $siteId, string $slug): int;

    public function countPlatformAdmins(): int;
}
