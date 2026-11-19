<?php

declare(strict_types=1);

namespace Nexis\Auth;

final readonly class OidcLoginPolicy
{
    public function __construct(
        public bool $enabled,
        public bool $autoProvision,
        public string $defaultRoleSlug = RoleSlug::MEMBER,
    ) {
    }
}
