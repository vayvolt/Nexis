<?php

declare(strict_types=1);

namespace Nexis\Api;

/**
 * Result of ApiTokenStore::create(): the stored token plus the plaintext secret,
 * which is never persisted and therefore only displayable once.
 */
final readonly class ApiTokenSecret
{
    public function __construct(
        public ApiToken $token,
        public string $plaintext,
    ) {
    }
}
