<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\I18n\PublicUi;
use Nexis\Mail\MailMessage;
use Nexis\Mail\MailPort;
use Nexis\Event\EventDispatcher;
use Nexis\Event\UserRegistered;
use Nexis\Site\Site;
use RuntimeException;

final class RegistrationService
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwords,
        private AuthSettings $settings,
        private MailPort $mail,
        private LoginService $login,
        private PublicUi $ui,
        private EventDispatcher $events,
    ) {
    }

    public function register(
        Site $site,
        string $email,
        string $displayName,
        string $password,
        string $appUrl,
        string $basePath,
        string $clientIp = '0.0.0.0',
    ): User {
        if (!$this->settings->registrationEnabled($site->id)) {
            throw new RuntimeException('auth.registration_disabled');
        }
        $email = mb_strtolower(trim($email));
        $displayName = trim($displayName);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('auth.invalid_email');
        }
        if ($displayName === '') {
            throw new RuntimeException('auth.invalid_name');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('auth.weak_password');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('auth.email_taken');
        }

        $roleId = $this->users->ensureMemberRole($site->id);
        $user = $this->users->createUser(
            $email,
            $this->passwords->hash($password),
            $displayName,
            false,
            $site->defaultLocale,
        );
        $this->users->setMembership($site->id, $user->id, $roleId);
        $this->users->markEmailVerified($user->id);
        $this->events->dispatch(new UserRegistered($site->id, $user->id, $email));

        try {
            $loginUrl = rtrim($appUrl !== '' ? $appUrl : $basePath, '/') . '/account/login';
            $this->mail->send(new MailMessage(
                [$email],
                $this->ui->get($site->defaultLocale, 'mail.welcome.subject', ['site' => $site->name]),
                $this->ui->get($site->defaultLocale, 'mail.welcome.body', [
                    'name' => $displayName,
                    'site' => $site->name,
                    'url' => $loginUrl,
                ]),
                null,
                null,
                $site->id->value,
                'account_welcome',
            ));
        } catch (\Throwable) {
            // Registration succeeds even if welcome mail fails (logged in mail_log).
        }

        $this->login->attempt($email, $password, $clientIp);

        return $user;
    }
}
