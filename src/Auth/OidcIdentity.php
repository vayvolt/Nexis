<?php

declare(strict_types=1);

namespace Nexis\Auth;

final class OidcIdentity
{
    public function __construct(
        public string $id,
        public UserId $userId,
        public string $provider,
        public string $subject,
        public ?string $emailAtLink,
    ) {
    }
}
