<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Mail\MailMessage;
use Nexis\Mail\MailPort;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Site\Site;
use RuntimeException;

final class PasswordResetService
{
    public function __construct(
        private UserRepository $users,
        private MembershipLookup $memberships,
        private PasswordHasher $passwords,
        private PdoPasswordResetRepository $tokens,
        private AuthSettings $settings,
        private MailPort $mail,
        private PublicUi $ui,
    ) {
    }

    /**
     * Always succeeds from the caller's perspective (no email enumeration).
     */
    public function request(Site $site, string $email, string $appUrl, string $basePath): void
    {
        if (!$this->settings->passwordResetEnabled($site->id)) {
            throw new RuntimeException('auth.reset_disabled');
        }
        $email = mb_strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return;
        }
        if (!$user->isPlatformAdmin && !$this->memberships->hasAccess($user->id, $site->id)) {
            return;
        }

        $this->tokens->invalidateOpenForUser($user->id);
        $created = $this->tokens->create($user->id, 3600);
        $base = rtrim($appUrl !== '' ? $appUrl : $basePath, '/');
        $resetUrl = $base . '/account/password/reset?token=' . urlencode($created['raw'])
            . '&lang=' . rawurlencode(Translator::normalizeUiLocale($user->uiLocale));

        $this->mail->send(new MailMessage(
            [$user->email],
            $this->ui->get($user->uiLocale, 'mail.password_reset.subject', ['site' => $site->name]),
            $this->ui->get($user->uiLocale, 'mail.password_reset.body', [
                'name' => $user->displayName,
                'url' => $resetUrl,
            ]),
            null,
            null,
            $site->id->value,
            'password_reset',
        ));
    }

    public function reset(string $rawToken, string $newPassword): User
    {
        if (strlen($newPassword) < 8) {
            throw new RuntimeException('auth.weak_password');
        }
        $row = $this->tokens->findValid($rawToken);
        if ($row === null) {
            throw new RuntimeException('auth.reset_invalid');
        }
        $user = $this->users->findById(new UserId($row['user_id']));
        if ($user === null) {
            throw new RuntimeException('auth.reset_invalid');
        }
        $this->users->updatePassword($user->id, $this->passwords->hash($newPassword));
        $this->tokens->markUsed($row['id']);
        $this->tokens->invalidateOpenForUser($user->id);

        return $user;
    }
}
