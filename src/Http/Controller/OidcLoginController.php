<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\AuthSettings;
use Nexis\Auth\OidcClient;
use Nexis\Auth\OidcLoginService;
use Nexis\Auth\SitePolicy;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\SessionStore;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Kernel\Config;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class OidcLoginController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private OidcClient $oidc,
        private OidcLoginService $oidcLogin,
        private AuthSettings $authSettings,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private SessionStore $session,
        private Config $config,
        private Translator $translator,
        private PublicUi $publicUi,
    ) {
    }

    public function start(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $site = $this->sites->installed();
        if ($site === null || !$this->authSettings->oidcEnabled($site->id)) {
            return $this->loginError($request, 'auth.oidc_disabled');
        }

        try {
            $redirectUri = $this->redirectUri($request, $basePath);
            $state = bin2hex(random_bytes(16));
            $nonce = bin2hex(random_bytes(16));
            $this->session->set('oidc_state', $state);
            $this->session->set('oidc_nonce', $nonce);
            $url = $this->oidc->authorizationUrl(
                $this->authSettings->oidcProviderConfig($site->id),
                $redirectUri,
                $state,
                $nonce,
            );

            return $this->responses->redirect($url);
        } catch (RuntimeException) {
            return $this->loginError($request, 'auth.oidc_start');
        }
    }

    public function callback(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $site = $this->sites->installed();
        if ($site === null || !$this->authSettings->oidcEnabled($site->id)) {
            return $this->loginError($request, 'auth.oidc_disabled');
        }

        $error = RequestInput::query($request, 'error');
        if ($error !== '') {
            return $this->loginError($request, 'auth.oidc_denied');
        }

        $state = RequestInput::query($request, 'state');
        $expected = $this->session->get('oidc_state');
        $this->session->remove('oidc_state');
        $this->session->remove('oidc_nonce');
        if (!is_string($expected) || $expected === '' || !hash_equals($expected, $state)) {
            return $this->loginError($request, 'auth.oidc_state');
        }

        $code = RequestInput::query($request, 'code');
        if ($code === '') {
            return $this->loginError($request, 'auth.oidc_code');
        }

        try {
            $claims = $this->oidc->authenticateWithCode(
                $this->authSettings->oidcProviderConfig($site->id),
                $code,
                $this->redirectUri($request, $basePath),
            );
            $user = $this->oidcLogin->loginWithClaims(
                $site,
                $this->authSettings->oidcLoginPolicy($site->id),
                $claims,
            );

            return $this->responses->redirect($this->postLoginPath($basePath, $user));
        } catch (RuntimeException $exception) {
            return $this->loginError($request, $exception->getMessage());
        }
    }

    private function redirectUri(ServerRequestInterface $request, string $basePath): string
    {
        $configured = rtrim((string) $this->config->get('app.url', ''), '/');
        if ($configured !== '') {
            return $configured . '/admin/login/oidc/callback';
        }

        $uri = $request->getUri();
        $origin = $uri->getScheme() . '://' . $uri->getHost();
        $port = $uri->getPort();
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $origin .= ':' . $port;
        }

        return $origin . $basePath . '/admin/login/oidc/callback';
    }

    private function loginError(ServerRequestInterface $request, string $code): ResponseInterface
    {
        $site = $this->sites->installed();
        $fallback = $site?->defaultLocale;
        $uiLocale = $this->publicUi->localeFromRequest($request, $fallback);
        $translator = $this->translator->withLocale($uiLocale);
        $t = static fn (string $key, array $replace = [], ?string $default = null): string => $translator->get($key, $replace, $default);

        $message = match ($code) {
            'auth.oidc_disabled', 'auth.oidc_not_configured' => $t('admin.login.oidc_disabled'),
            'auth.oidc_no_account' => $t('admin.login.oidc_no_account'),
            'auth.oidc_email_unverified' => $t('admin.login.oidc_email_unverified'),
            'auth.oidc_denied' => $t('admin.login.oidc_denied'),
            default => $t('admin.login.oidc_failed'),
        };

        $html = $this->views->render('admin.login', [
            'error' => $message,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'basePath' => (string) $request->getAttribute('base_path', ''),
            'uiLocale' => $uiLocale,
            'translator' => $translator,
            't' => $t,
            'oidcEnabled' => $site !== null && $this->authSettings->oidcEnabled($site->id),
            'oidcButtonLabel' => $site !== null ? $this->authSettings->oidcButtonLabel($site->id) : '',
        ]);

        return $this->responses->html($html, 401);
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
