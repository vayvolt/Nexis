<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Audit\AuditLogger;
use Nexis\Event\EventDispatcher;
use Nexis\Event\UserAuthenticated;
use Nexis\Http\SessionStore;
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

        if ($user->hasTotp()) {
            $this->session->set('pending_2fa_user_id', $user->id->value);
            $this->audit->log('auth.login.totp_required', actorId: $user->id);
            throw new RuntimeException('auth.totp_required');
        }

        $this->completeLogin($user);

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
        $this->completeLogin($user);

        return $user;
    }

    public function logout(): void
    {
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

    private function completeLogin(User $user): void
    {
        $this->session->set('user_id', $user->id->value);
        $this->users->save($user);
        $this->audit->log('auth.login.success', actorId: $user->id);
        $site = $this->sites->installed();
        $this->events->dispatch(new UserAuthenticated(
            $user->id,
            $site?->id,
            'session',
        ));
    }
}
