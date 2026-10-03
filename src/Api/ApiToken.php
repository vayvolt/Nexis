<?php

declare(strict_types=1);

namespace Nexis\Api;

use DateTimeImmutable;
use Nexis\Auth\UserId;
use Nexis\Site\SiteId;

/**
 * Stored API token. The plaintext secret only exists once at creation time.
 */
final readonly class ApiToken
{
    /**
     * @param list<string> $scopes Empty list = all permissions of the owning user at use time.
     */
    public function __construct(
        public string $id,
        public SiteId $siteId,
        public UserId $userId,
        public string $name,
        public string $tokenPrefix,
        public array $scopes,
        public ?DateTimeImmutable $lastUsedAt,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $revokedAt,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    public function isUsableAt(DateTimeImmutable $now): bool
    {
        return !$this->isRevoked() && !$this->isExpiredAt($now);
    }

    public function allowsScope(string $permission): bool
    {
        return $this->scopes === [] || in_array($permission, $this->scopes, true);
    }
}
