<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\PasswordHasher;
use Nexis\Auth\Totp;
use Nexis\Auth\User;
use Nexis\Auth\UserRepository;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\SessionStore;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SecurityAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private UserRepository $users,
        private SessionStore $session,
        private AuditLogger $audit,
        private AdminUi $ui,
        private PasswordHasher $passwords,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        $pending = $this->session->get('pending_totp_secret');
        $uri = is_string($pending) && $pending !== ''
            ? Totp::provisioningUri($pending, $user->email)
            : '';
        $qrSvg = '';
        if ($uri !== '') {
            $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
            $qrSvg = $writer->writeString($uri);
        }

        $errorKey = RequestInput::query($request, 'error');
        $error = match ($errorKey) {
            '1', 'code' => $this->ui->get($user, 'admin.error.security_code_invalid'),
            'password' => $this->ui->get($user, 'admin.security.totp_disable_password_invalid'),
            default => '',
        };

        return $this->responses->html($this->views->render('admin.security.index', [
            'user' => $user,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'enabled' => $user->hasTotp(),
            'pendingSecret' => is_string($pending) ? $pending : '',
            'otpauthUri' => $uri,
            'qrSvg' => $qrSvg,
            'notice' => RequestInput::query($request, 'saved') === '1'
                ? $this->ui->get($user, 'admin.common.saved')
                : '',
            'error' => $error,
        ], 'admin.layout'));
    }

    public function startTotp(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        $this->session->set('pending_totp_secret', Totp::generateSecret());

        return $this->responses->redirect($basePath . '/admin/security');
    }

    public function confirmTotp(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        $pending = $this->session->get('pending_totp_secret');
        $code = RequestInput::string($request, 'code');
        if (!is_string($pending) || $pending === '' || !Totp::verify($pending, $code)) {
            return $this->responses->redirect($basePath . '/admin/security?error=code');
        }
        $this->users->updateTotpSecret($user->id, $pending);
        $this->session->remove('pending_totp_secret');
        $this->audit->log('auth.totp.enabled', actorId: $user->id);

        return $this->responses->redirect($basePath . '/admin/security?saved=1');
    }

    public function disableTotp(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        if (!$user->hasTotp()) {
            return $this->responses->redirect($basePath . '/admin/security');
        }

        $password = RequestInput::string($request, 'password');
        $code = RequestInput::string($request, 'code');
        if (!$this->passwords->verify($password, $user->passwordHash)) {
            return $this->responses->redirect($basePath . '/admin/security?error=password');
        }
        if (!Totp::verify((string) $user->totpSecret, $code)) {
            return $this->responses->redirect($basePath . '/admin/security?error=code');
        }

        $this->users->updateTotpSecret($user->id, null);
        $this->session->remove('pending_totp_secret');
        $this->audit->log('auth.totp.disabled', actorId: $user->id);

        return $this->responses->redirect($basePath . '/admin/security?saved=1');
    }
}
