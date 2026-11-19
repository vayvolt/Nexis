<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Audit\AuditLogger;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Kernel\Nexis;
use Nexis\Mail\MailMessage;
use Nexis\Mail\MailPort;
use Nexis\Mail\TransactionalMailHtml;
use Nexis\Site\Site;
use Nexis\Site\SiteId;
use Nexis\Site\SiteRepository;
use RuntimeException;

final class UserInviteService
{
    public function __construct(
        private UserRepository $users,
        private PdoUserInviteRepository $invites,
        private PasswordHasher $passwords,
        private MailPort $mail,
        private PublicUi $ui,
        private AuditLogger $audit,
        private LoginService $login,
        private SiteRepository $sites,
    ) {
    }

    /**
     * @return array{id: string, raw: string}
     */
    public function invite(
        Site $site,
        User $actor,
        string $email,
        string $displayName,
        string $roleId,
        bool $isPlatformAdmin,
        string $appUrl,
        string $basePath,
    ): array {
        $email = mb_strtolower(trim($email));
        $displayName = trim($displayName);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('auth.invalid_email');
        }
        if ($displayName === '') {
            throw new RuntimeException('auth.invalid_name');
        }
        if ($roleId === '') {
            throw new RuntimeException('auth.invite_role');
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('auth.email_taken');
        }

        $this->invites->invalidateOpenForEmail($site->id, $email);
        $created = $this->invites->create(
            $site->id,
            $email,
            $displayName,
            $roleId,
            $isPlatformAdmin,
            $actor->id,
        );

        $base = rtrim($appUrl !== '' ? $appUrl : $basePath, '/');
        $locale = Translator::normalizeUiLocale($site->defaultLocale);
        $url = $base . '/account/invite?token=' . urlencode($created['raw'])
            . '&lang=' . rawurlencode($locale);
        $replace = [
            'name' => $displayName,
            'site' => $site->name,
            'url' => $url,
            'actor' => $actor->displayName,
            'product' => Nexis::NAME,
        ];
        $subject = $this->ui->get($locale, 'mail.invite.subject', $replace);
        $textBody = $this->ui->get($locale, 'mail.invite.body', $replace);
        $heading = $this->ui->get($locale, 'mail.invite.heading', $replace);
        $preheader = $this->ui->get($locale, 'mail.invite.preheader', $replace);
        $inner = TransactionalMailHtml::paragraph($this->ui->get($locale, 'mail.invite.greeting', $replace));
        $inner .= TransactionalMailHtml::paragraph($this->ui->get($locale, 'mail.invite.lead', $replace));
        $inner .= TransactionalMailHtml::button(
            $this->ui->get($locale, 'mail.invite.cta'),
            $url,
        );
        $inner .= TransactionalMailHtml::paragraph($this->ui->get($locale, 'mail.invite.expiry', $replace));
        $inner .= TransactionalMailHtml::footer($this->ui->get($locale, 'mail.invite.footer', $replace));
        $htmlBody = TransactionalMailHtml::wrap($heading, $preheader, $inner, Nexis::NAME);

        $this->mail->send(new MailMessage(
            [$email],
            $subject,
            $textBody,
            $htmlBody,
            null,
            $site->id->value,
            'user_invite',
        ));

        $this->audit->log(
            'auth.invite.sent',
            siteId: $site->id,
            actorId: $actor->id,
            context: ['email' => $email, 'role_id' => $roleId],
        );

        return $created;
    }

    /**
     * @return array{
     *   id: string,
     *   site_id: string,
     *   email: string,
     *   display_name: string,
     *   role_id: string,
     *   is_platform_admin: bool
     * }|null
     */
    public function peek(string $rawToken): ?array
    {
        return $this->invites->findValid($rawToken);
    }

    public function revoke(Site $site, User $actor, string $inviteId): void
    {
        $inviteId = trim($inviteId);
        if ($inviteId === '') {
            throw new RuntimeException('auth.invite_invalid');
        }
        $revoked = $this->invites->revokeOpen($site->id, $inviteId);
        if ($revoked === null) {
            throw new RuntimeException('auth.invite_invalid');
        }
        $this->audit->log(
            'auth.invite.revoked',
            siteId: $site->id,
            actorId: $actor->id,
            context: [
                'invite_id' => $revoked['id'],
                'email' => $revoked['email'],
            ],
        );
    }

    public function accept(
        string $rawToken,
        string $displayName,
        string $password,
        string $clientIp = '0.0.0.0',
    ): User {
        $invite = $this->invites->findValid($rawToken);
        if ($invite === null) {
            throw new RuntimeException('auth.invite_invalid');
        }
        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $invite['display_name'];
        }
        if ($displayName === '') {
            throw new RuntimeException('auth.invalid_name');
        }
        if (strlen($password) < 8) {
            throw new RuntimeException('auth.weak_password');
        }
        if ($this->users->findByEmail($invite['email']) !== null) {
            throw new RuntimeException('auth.email_taken');
        }

        $siteId = new SiteId($invite['site_id']);
        $site = $this->sites->findById($siteId);
        $uiLocale = $site !== null ? $site->defaultLocale : 'de';
        $user = $this->users->createUser(
            $invite['email'],
            $this->passwords->hash($password),
            $displayName,
            $invite['is_platform_admin'],
            $uiLocale,
        );
        $this->users->setMembership($siteId, $user->id, $invite['role_id']);
        $this->users->markEmailVerified($user->id);
        $this->invites->markAccepted($invite['id']);
        $this->audit->log(
            'auth.invite.accepted',
            siteId: $siteId,
            actorId: $user->id,
            context: ['email' => $invite['email']],
        );

        return $this->login->completeExternalLogin($user, 'invite');
    }
}
