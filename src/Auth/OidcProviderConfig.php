<?php

declare(strict_types=1);

namespace Nexis\Auth;

final readonly class OidcProviderConfig
{
    public function __construct(
        public string $issuer,
        public string $clientId,
        public string $clientSecret,
    ) {
    }

    public function isComplete(): bool
    {
        return $this->issuer !== '' && $this->clientId !== '' && $this->clientSecret !== '';
    }
}
