<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Audit\AuditLogger;
use Nexis\Event\EventDispatcher;
use Nexis\Event\UserAuthenticated;
use Nexis\Http\SessionStore;
use Nexis\I18n\Translator;
use Nexis\Site\SiteRepository;
use RuntimeException;

final class LoginService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private SessionStore $session,
        private AuditLogger $audit,
        private AuthThrottle $throttle,
        private EventDispatcher $events,
        private SiteRepository $sites,
        private WebAuthnService $webauthn,
    ) {
    }

    public function attempt(string $email, string $password, string $clientIp = '0.0.0.0'): User
    {
        $email = trim($email);
        $this->throttle->assertNotLocked($email, $clientIp);

        $user = $this->users->findByEmail($email);
        if ($user === null || !$this->passwords->verify($password, $user->passwordHash)) {
            $this->throttle->recordFailure($email, $clientIp);
            $this->audit->log('auth.login.failed', context: ['email' => $email]);
            // Re-check in case this failure just triggered the lock.
            $this->throttle->assertNotLocked($email, $clientIp);
            throw new RuntimeException('auth.invalid');
        }

        $this->throttle->clear($email, $clientIp);
        $this->session->regenerate();
        $this->session->remove('login_attempts');
        $this->session->remove('login_locked_until');

        if ($user->hasTotp() || $this->webauthn->hasCredentials($user->id)) {
            $this->session->set('pending_2fa_user_id', $user->id->value);
            $this->audit->log('auth.login.second_factor_required', actorId: $user->id);
            throw new RuntimeException('auth.totp_required');
        }

        $this->completeLogin($user, 'session');

        return $user;
    }

    public function verifyTotp(string $code): User
    {
        $pending = $this->session->get('pending_2fa_user_id');
        if (!is_string($pending) || $pending === '') {
            throw new RuntimeException('auth.totp_session');
        }
        $user = $this->users->findById(new UserId($pending));
        if ($user === null || !$user->hasTotp() || !Totp::verify((string) $user->totpSecret, $code)) {
            $this->audit->log('auth.login.totp_failed', actorId: $user?->id);
            throw new RuntimeException('auth.totp_invalid');
        }
        $this->session->remove('pending_2fa_user_id');
        $this->completeLogin($user, 'session');

        return $user;
    }

    public function verifyPasskey(
        string $credentialIdB64,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
    ): User {
        $pending = $this->session->get('pending_2fa_user_id');
        if (!is_string($pending) || $pending === '') {
            throw new RuntimeException('auth.totp_session');
        }
        $userId = $this->webauthn->finishAssertion(
            $credentialIdB64,
            $clientDataJSON,
            $authenticatorData,
            $signature,
        );
        if ($userId->value !== $pending) {
            throw new RuntimeException('auth.webauthn_user');
        }
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new RuntimeException('auth.webauthn_user');
        }
        $this->session->remove('pending_2fa_user_id');
        $this->completeLogin($user, 'passkey');

        return $user;
    }

    public function pendingSecondFactorUser(): ?User
    {
        $pending = $this->session->get('pending_2fa_user_id');
        if (!is_string($pending) || $pending === '') {
            return null;
        }

        return $this->users->findById(new UserId($pending));
    }

    /**
     * Finish login for an already-authenticated external identity (OIDC).
     * Skips local TOTP — the IdP is the MFA boundary for this path.
     */
    public function completeExternalLogin(User $user, string $channel = 'oidc'): User
    {
        $this->session->regenerate();
        $this->session->remove('pending_2fa_user_id');
        $this->session->remove('login_attempts');
        $this->session->remove('login_locked_until');
        $this->completeLogin($user, $channel);

        return $user;
    }

    public function logout(): void
    {
        // Keep the account UI language for the guest session (do not fall back to Accept-Language).
        $user = $this->current();
        if ($user !== null && $user->uiLocale !== '') {
            $this->session->set('_nexis_ui_locale', Translator::normalizeUiLocale($user->uiLocale));
        }
        $this->session->remove('user_id');
        $this->session->remove('pending_2fa_user_id');
        $this->session->regenerate();
    }

    public function current(): ?User
    {
        $id = $this->session->get('user_id');
        if (!is_string($id) || $id === '') {
            return null;
        }

        try {
            return $this->users->findById(new UserId($id));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function completeLogin(User $user, string $channel): void
    {
        $this->session->set('user_id', $user->id->value);
        $this->users->save($user);
        $this->audit->log('auth.login.success', actorId: $user->id, context: ['channel' => $channel]);
        $site = $this->sites->installed();
        $this->events->dispatch(new UserAuthenticated(
            $user->id,
            $site?->id,
            $channel,
        ));
    }
}
