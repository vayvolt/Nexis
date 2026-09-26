<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\LoginService;
use Nexis\Auth\SitePolicy;
use Nexis\Http\RequestInput;
use Nexis\Http\RequestSecurity;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class LoginController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private LoginService $login,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private RequestSecurity $security,
        private Translator $translator,
        private PublicUi $publicUi,
    ) {
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->views->render('admin.login', $this->loginViewData($request, ''));

        return $this->responses->html($html);
    }

    public function submit(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        try {
            $user = $this->login->attempt(
                RequestInput::string($request, 'email'),
                RequestInput::string($request, 'password'),
                $this->security->clientIp($request),
            );

            return $this->responses->redirect($this->postLoginPath($basePath, $user));
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'auth.totp_required') {
                return $this->responses->redirect($basePath . '/admin/login/2fa');
            }
            $data = $this->loginViewData($request, '');
            $t = $data['t'];
            $message = $exception->getMessage() === 'auth.locked'
                ? (is_callable($t) ? $t('admin.login.error_locked') : 'Zu viele Versuche. Bitte später erneut versuchen.')
                : (is_callable($t) ? $t('admin.login.error_credentials') : 'E-Mail oder Passwort ist falsch.');
            $html = $this->views->render('admin.login', $this->loginViewData($request, $message));

            return $this->responses->html($html, 401);
        }
    }

    public function showTotp(ServerRequestInterface $request): ResponseInterface
    {
        $html = $this->views->render('admin.login_2fa', $this->loginViewData($request, ''));

        return $this->responses->html($html);
    }

    public function submitTotp(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        try {
            $user = $this->login->verifyTotp(RequestInput::string($request, 'code'));

            return $this->responses->redirect($this->postLoginPath($basePath, $user));
        } catch (RuntimeException) {
            $data = $this->loginViewData($request, '');
            $t = $data['t'];
            $data['error'] = is_callable($t) ? $t('admin.login.2fa_invalid') : 'Ungültiger Authentifizierungscode.';
            $html = $this->views->render('admin.login_2fa', $data);

            return $this->responses->html($html, 401);
        }
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $this->login->logout();

        return $this->responses->redirect((string) $request->getAttribute('base_path', '') . '/admin/login');
    }

    /**
     * @return array<string, mixed>
     */
    private function loginViewData(ServerRequestInterface $request, string $error): array
    {
        $site = $this->sites->installed();
        $fallback = $site?->defaultLocale;
        $uiLocale = $this->publicUi->localeFromRequest($request, $fallback);
        $translator = $this->translator->withLocale($uiLocale);

        return [
            'error' => $error,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'basePath' => (string) $request->getAttribute('base_path', ''),
            'uiLocale' => $uiLocale,
            'translator' => $translator,
            't' => static fn (string $key, array $replace = [], ?string $default = null): string => $translator->get($key, $replace, $default),
        ];
    }

    private function postLoginPath(string $basePath, \Nexis\Auth\User $user): string
    {
        $site = $this->sites->installed();
        if ($site !== null && !$this->policy->canAccessCms($user, $site)) {
            return $basePath . '/account';
        }

        return $basePath . '/admin';
    }
}
