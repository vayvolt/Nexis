<?php

declare(strict_types=1);

namespace Nexis\Auth;

interface OidcIdentityRepository
{
    public function findByProviderSubject(string $provider, string $subject): ?OidcIdentity;

    public function link(UserId $userId, string $provider, string $subject, ?string $emailAtLink): OidcIdentity;
}
