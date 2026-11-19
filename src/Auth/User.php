<?php

declare(strict_types=1);

namespace Nexis\Auth;

final class User
{
    public function __construct(
        public private(set) UserId $id,
        public private(set) string $email,
        public private(set) string $passwordHash,
        public private(set) string $displayName,
        public private(set) bool $isPlatformAdmin,
        public private(set) string $uiLocale,
        public private(set) ?string $totpSecret = null,
    ) {
    }

    public function hasTotp(): bool
    {
        return is_string($this->totpSecret) && $this->totpSecret !== '';
    }

    public function withTotpSecret(?string $secret): self
    {
        return new self(
            $this->id,
            $this->email,
            $this->passwordHash,
            $this->displayName,
            $this->isPlatformAdmin,
            $this->uiLocale,
            $secret,
        );
    }
}
