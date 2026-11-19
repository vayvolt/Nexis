<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Site\Site;
use RuntimeException;

/**
 * Resolve a local user from OIDC claims and finish the session login.
 */
final class OidcLoginService
{
    public function __construct(
        private OidcIdentityRepository $identities,
        private UserRepository $users,
        private PasswordHasher $passwords,
        private LoginService $login,
    ) {
    }

    /**
     * @param array{sub: string, email: string, email_verified: bool, name: string} $claims
     */
    public function loginWithClaims(Site $site, OidcLoginPolicy $policy, array $claims): User
    {
        if (!$policy->enabled) {
            throw new RuntimeException('auth.oidc_disabled');
        }

        $provider = OidcClient::PROVIDER;
        $identity = $this->identities->findByProviderSubject($provider, $claims['sub']);
        if ($identity !== null) {
            $user = $this->users->findById($identity->userId);
            if ($user === null) {
                throw new RuntimeException('auth.oidc_user_missing');
            }

            return $this->login->completeExternalLogin($user, 'oidc');
        }

        $existing = $this->users->findByEmail($claims['email']);
        if ($existing !== null) {
            $this->identities->link($existing->id, $provider, $claims['sub'], $claims['email']);

            return $this->login->completeExternalLogin($existing, 'oidc');
        }

        if (!$policy->autoProvision) {
            throw new RuntimeException('auth.oidc_no_account');
        }

        $roleSlug = $policy->defaultRoleSlug;
        $roleId = $this->users->findRoleIdBySlug($site->id, $roleSlug);
        if ($roleId === null) {
            $roleId = $this->users->ensureMemberRole($site->id);
        }

        $user = $this->users->createUser(
            $claims['email'],
            $this->passwords->hash(bin2hex(random_bytes(32))),
            $claims['name'],
            false,
            $site->defaultLocale,
        );
        $this->users->setMembership($site->id, $user->id, $roleId);
        $this->users->markEmailVerified($user->id);
        $this->identities->link($user->id, $provider, $claims['sub'], $claims['email']);

        return $this->login->completeExternalLogin($user, 'oidc');
    }
}
