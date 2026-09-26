<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Nexis\Audit\AuditLogger;
use Nexis\Auth\AccountNavLinks;
use Nexis\Auth\AuthSettings;
use Nexis\Auth\LoginService;
use Nexis\Auth\PasswordHasher;
use Nexis\Auth\PasswordResetService;
use Nexis\Auth\RegistrationService;
use Nexis\Auth\Totp;
use Nexis\Auth\User;
use Nexis\Auth\UserRepository;
use Nexis\Content\MenuRepository;
use Nexis\Http\RequestInput;
use Nexis\Http\RequestSecurity;
use Nexis\Http\ResponseFactory;
use Nexis\Http\SessionStore;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Kernel\Config;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeService;
use Nexis\Theme\ThemeViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class AccountController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private LoginService $login,
        private RegistrationService $registration,
        private PasswordResetService $passwordReset,
        private AuthSettings $authSettings,
        private UserRepository $users,
        private PasswordHasher $passwords,
        private Config $config,
        private RequestSecurity $security,
        private ThemeService $themes,
        private ThemeViewRenderer $themeViews,
        private MenuRepository $menus,
        private AccountNavLinks $accountNav,
        private LocalePathResolver $paths,
        private SessionStore $session,
        private AuditLogger $audit,
        private Translator $translator,
        private AdminUi $ui,
        private PublicUi $publicUi,
    ) {
    }

    public function dashboard(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $user = $this->requireUser($request, $basePath);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $pending = $this->session->get('pending_totp_secret');
        $issuer = $site->name !== '' ? $site->name : 'Nexis';
        $uri = is_string($pending) && $pending !== ''
            ? Totp::provisioningUri($pending, $user->email, $issuer)
            : '';
        $qrSvg = '';
        if ($uri !== '') {
            $writer = new Writer(new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd()));
            $qrSvg = $writer->writeString($uri);
        }

        $roleSlug = $this->users->roleSlugFor($user->id, $site->id);
        $t = $this->translatorFor($request, $user);
        $roleLabel = match ($roleSlug) {
            'admin' => $t->get('account.role.admin'),
            'editor' => $t->get('account.role.editor'),
            'seo' => $t->get('account.role.seo'),
            'member' => $t->get('account.role.member'),
            default => $roleSlug ?? $t->get('account.role.none'),
        };

        $errorKey = RequestInput::query($request, 'error');
        $error = $errorKey !== ''
            ? $t->get('account.error.' . $errorKey, [], $errorKey)
            : '';

        return $this->renderAccount($request, $site, $basePath, 'account.dashboard', [
            'user' => $user,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'saved' => RequestInput::query($request, 'saved'),
            'error' => $error,
            'locales' => $site->enabledLocales(),
            'roleLabel' => $roleLabel,
            'totpEnabled' => $user->hasTotp(),
            'pendingSecret' => is_string($pending) ? $pending : '',
            'otpauthUri' => $uri,
            'qrSvg' => $qrSvg,
            'canDeleteAccount' => !$user->isPlatformAdmin,
        ], $t->get('account.dashboard.title'), userLocale: $user->uiLocale);
    }

    public function showLogin(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        if ($this->login->current() instanceof User) {
            return $this->responses->redirect($basePath . '/account');
        }

        $t = $this->translatorFor($request);

        return $this->renderAccount($request, $site, $basePath, 'account.login', [
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => '',
            'deleted' => RequestInput::query($request, 'deleted') === '1',
            'registrationEnabled' => $this->authSettings->registrationEnabled($site->id),
            'resetEnabled' => $this->authSettings->passwordResetEnabled($site->id),
        ], $t->get('account.login.title'));
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $t = $this->translatorFor($request);
        try {
            $this->login->attempt(
                RequestInput::string($request, 'email'),
                RequestInput::string($request, 'password'),
                $this->security->clientIp($request),
            );

            return $this->responses->redirect($basePath . '/account');
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'auth.totp_required') {
                return $this->responses->redirect($basePath . '/account/login/2fa');
            }
            $message = $e->getMessage() === 'auth.locked'
                ? $t->get('account.login.locked')
                : $t->get('account.login.credentials');

            return $this->renderAccount($request, $site, $basePath, 'account.login', [
                'csrf' => (string) $request->getAttribute('csrf', ''),
                'error' => $message,
                'registrationEnabled' => $this->authSettings->registrationEnabled($site->id),
                'resetEnabled' => $this->authSettings->passwordResetEnabled($site->id),
            ], $t->get('account.login.title'), 401);
        }
    }

    public function showLoginTotp(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        if ($this->login->current() instanceof User) {
            return $this->responses->redirect($basePath . '/account');
        }
        $pending = $this->session->get('pending_2fa_user_id');
        if (!is_string($pending) || $pending === '') {
            return $this->responses->redirect($basePath . '/account/login');
        }

        $t = $this->translatorFor($request);

        return $this->renderAccount($request, $site, $basePath, 'account.login_2fa', [
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => '',
        ], $t->get('account.2fa.title'));
    }

    public function submitLoginTotp(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $t = $this->translatorFor($request);
        try {
            $this->login->verifyTotp(RequestInput::string($request, 'code'));

            return $this->responses->redirect($basePath . '/account');
        } catch (RuntimeException) {
            return $this->renderAccount($request, $site, $basePath, 'account.login_2fa', [
                'csrf' => (string) $request->getAttribute('csrf', ''),
                'error' => $t->get('account.2fa.invalid'),
            ], $t->get('account.2fa.title'), 401);
        }
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $this->login->logout();

        return $this->responses->redirect($basePath . '/account/login');
    }

    public function showRegister(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $t = $this->translatorFor($request);
        if (!$this->authSettings->registrationEnabled($site->id)) {
            return $this->renderAccount($request, $site, $basePath, 'account.register_closed', [
                'csrf' => (string) $request->getAttribute('csrf', ''),
            ], $t->get('account.register.closed_title'), 403);
        }
        if ($this->login->current() instanceof User) {
            return $this->responses->redirect($basePath . '/account');
        }

        return $this->renderAccount($request, $site, $basePath, 'account.register', [
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => '',
        ], $t->get('account.register.title'));
    }

    public function register(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $t = $this->translatorFor($request);
        try {
            $this->registration->register(
                $site,
                RequestInput::string($request, 'email'),
                RequestInput::string($request, 'display_name'),
                RequestInput::string($request, 'password'),
                (string) $this->config->get('app.url', ''),
                $basePath,
                $this->security->clientIp($request),
            );

            return $this->responses->redirect($basePath . '/account?saved=profile');
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'auth.registration_disabled' => $t->get('account.register.disabled'),
                'auth.invalid_email' => $t->get('account.error.email_invalid'),
                'auth.invalid_name' => $t->get('account.register.invalid_name'),
                'auth.weak_password' => $t->get('account.error.password_min'),
                'auth.email_taken' => $t->get('account.register.email_taken'),
                default => $t->get('account.register.failed'),
            };

            return $this->renderAccount($request, $site, $basePath, 'account.register', [
                'csrf' => (string) $request->getAttribute('csrf', ''),
                'error' => $message,
            ], $t->get('account.register.title'), 422);
        }
    }

    public function showForgot(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $t = $this->translatorFor($request);
        if (!$this->authSettings->passwordResetEnabled($site->id)) {
            return $this->responses->html($t->get('account.error.reset_disabled'), 403);
        }

        return $this->renderAccount($request, $site, $basePath, 'account.forgot', [
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => '',
            'sent' => RequestInput::query($request, 'sent') === '1',
        ], $t->get('account.forgot.title'));
    }

    public function forgot(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $t = $this->translatorFor($request);
        try {
            $this->passwordReset->request(
                $site,
                RequestInput::string($request, 'email'),
                (string) $this->config->get('app.url', ''),
                $basePath,
            );
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'auth.reset_disabled') {
                return $this->responses->html($t->get('account.error.reset_disabled'), 403);
            }
            throw $e;
        }

        return $this->responses->redirect($basePath . '/account/password/forgot?sent=1&lang=' . rawurlencode($t->locale()));
    }

    public function showReset(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $token = RequestInput::query($request, 'token');
        $t = $this->translatorFor($request);

        return $this->renderAccount($request, $site, $basePath, 'account.reset', [
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'token' => $token,
            'error' => '',
        ], $t->get('account.reset.title'));
    }

    public function reset(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $token = RequestInput::string($request, 'token');
        $password = RequestInput::string($request, 'password');
        $confirm = RequestInput::string($request, 'password_confirmation');
        $t = $this->translatorFor($request);
        if ($password !== $confirm) {
            return $this->renderAccount($request, $site, $basePath, 'account.reset', [
                'csrf' => (string) $request->getAttribute('csrf', ''),
                'token' => $token,
                'error' => $t->get('account.error.password_mismatch'),
            ], $t->get('account.reset.title'), 422);
        }
        try {
            $user = $this->passwordReset->reset($token, $password);
            $this->login->attempt($user->email, $password, $this->security->clientIp($request));

            return $this->responses->redirect($basePath . '/account?saved=password');
        } catch (RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'auth.weak_password' => $t->get('account.error.password_min'),
                'auth.reset_invalid' => $t->get('account.error.reset_invalid'),
                default => $t->get('account.error.reset_failed'),
            };

            return $this->renderAccount($request, $site, $basePath, 'account.reset', [
                'csrf' => (string) $request->getAttribute('csrf', ''),
                'token' => $token,
                'error' => $message,
            ], $t->get('account.reset.title'), 422);
        }
    }

    public function updateProfile(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $user = $this->requireUser($request, $basePath);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $name = trim(RequestInput::string($request, 'display_name'));
        $email = trim(RequestInput::string($request, 'email'));
        $locale = trim(RequestInput::string($request, 'ui_locale'));
        if ($name === '') {
            return $this->responses->redirect($basePath . '/account?error=name_required');
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->responses->redirect($basePath . '/account?error=email_invalid');
        }
        $allowedLocales = array_map(
            static fn ($loc): string => $loc->locale,
            $site->enabledLocales(),
        );
        if ($allowedLocales === []) {
            $allowedLocales = [$site->defaultLocale];
        }
        if (!in_array($locale, $allowedLocales, true)) {
            $locale = $site->defaultLocale;
        }
        $existing = $this->users->findByEmail($email);
        if ($existing !== null && $existing->id->value !== $user->id->value) {
            return $this->responses->redirect($basePath . '/account?error=email_taken');
        }

        $this->users->updateAccountProfile($user->id, $name, $email, $locale);

        return $this->responses->redirect($basePath . '/account?saved=profile');
    }

    public function updatePassword(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$site, $basePath] = $ctx;
        $user = $this->requireUser($request, $basePath);
        if ($user instanceof ResponseInterface) {
            return $user;
        }

        $current = RequestInput::string($request, 'current_password');
        $password = RequestInput::string($request, 'password');
        $confirm = RequestInput::string($request, 'password_confirmation');
        if (!$this->passwords->verify($current, $user->passwordHash)) {
            return $this->responses->redirect($basePath . '/account?error=current_password');
        }
        if (strlen($password) < 8) {
            return $this->responses->redirect($basePath . '/account?error=password_min');
        }
        if ($password !== $confirm) {
            return $this->responses->redirect($basePath . '/account?error=password_mismatch');
        }

        $this->users->updatePassword($user->id, $this->passwords->hash($password));
        $this->audit->log('account.password.changed', actorId: $user->id);

        return $this->responses->redirect($basePath . '/account?saved=password');
    }

    public function startTotp(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $basePath] = $ctx;
        $user = $this->requireUser($request, $basePath);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $this->session->set('pending_totp_secret', Totp::generateSecret());

        return $this->responses->redirect($basePath . '/account#security');
    }

    public function confirmTotp(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $basePath] = $ctx;
        $user = $this->requireUser($request, $basePath);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $pending = $this->session->get('pending_totp_secret');
        $code = RequestInput::string($request, 'code');
        if (!is_string($pending) || $pending === '' || !Totp::verify($pending, $code)) {
            return $this->responses->redirect($basePath . '/account?error=2fa_invalid#security');
        }
        $this->users->updateTotpSecret($user->id, $pending);
        $this->session->remove('pending_totp_secret');
        $this->audit->log('auth.totp.enabled', actorId: $user->id);

        return $this->responses->redirect($basePath . '/account?saved=2fa#security');
    }

    public function disableTotp(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $basePath] = $ctx;
        $user = $this->requireUser($request, $basePath);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        $password = RequestInput::string($request, 'password');
        if (!$this->passwords->verify($password, $user->passwordHash)) {
            return $this->responses->redirect($basePath . '/account?error=disable_password#security');
        }
        $this->users->updateTotpSecret($user->id, null);
        $this->session->remove('pending_totp_secret');
        $this->audit->log('auth.totp.disabled', actorId: $user->id);

        return $this->responses->redirect($basePath . '/account?saved=2fa#security');
    }

    public function deleteAccount(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->siteContext($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [, $basePath] = $ctx;
        $user = $this->requireUser($request, $basePath);
        if ($user instanceof ResponseInterface) {
            return $user;
        }
        if ($user->isPlatformAdmin) {
            return $this->responses->redirect($basePath . '/account?error=platform_admin_delete');
        }
        $password = RequestInput::string($request, 'password');
        $confirm = RequestInput::string($request, 'confirm');
        if ($confirm !== 'DELETE') {
            return $this->responses->redirect($basePath . '/account?error=delete_confirm');
        }
        if (!$this->passwords->verify($password, $user->passwordHash)) {
            return $this->responses->redirect($basePath . '/account?error=password_invalid');
        }
        $this->audit->log('account.deleted', actorId: $user->id, context: ['email' => $user->email]);
        $this->users->softDelete($user->id);
        $this->login->logout();

        return $this->responses->redirect($basePath . '/account/login?deleted=1');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderAccount(
        ServerRequestInterface $request,
        Site $site,
        string $basePath,
        string $view,
        array $data,
        string $title,
        int $status = 200,
        ?string $userLocale = null,
    ): ResponseInterface {
        $userForUi = isset($data['user']) && $data['user'] instanceof User ? $data['user'] : null;
        $translator = $this->translatorFor($request, $userForUi);
        $uiLocale = $translator->locale();
        $data['site'] = $site;
        $data['basePath'] = $basePath;
        $data['uiLocale'] = $uiLocale;
        $data['translator'] = $translator;
        $data['t'] = static fn (string $key, array $replace = [], ?string $default = null): string => $translator->get($key, $replace, $default);
        $contentHtml = $this->views->render($view, $data);
        $locale = $userLocale;
        if ($locale === null) {
            $queryLocale = trim(RequestInput::query($request, 'locale'));
            if ($queryLocale !== '' && $site->locale($queryLocale) !== null) {
                $locale = $queryLocale;
            } elseif ($site->locale($uiLocale) !== null) {
                $locale = $uiLocale;
            } else {
                $locale = $site->defaultLocale;
            }
        }
        if ($site->locale($locale) === null) {
            $locale = $site->defaultLocale;
        }
        $scheme = $request->getUri()->getScheme() ?: null;

        try {
            $theme = $this->themes->resolveFor($site, $basePath);
            $template = $this->themes->resolveTemplate($theme['manifest'], 'account');
            $primaryNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'primary', $locale, $basePath),
            );
            $footerNav = array_map(
                static fn ($link): array => $link->toArray(),
                $this->menus->resolveLinks($site, 'footer', $locale, $basePath),
            );
            $alternates = [];
            $accountPath = $request->getUri()->getPath();
            if ($accountPath === '') {
                $accountPath = $basePath . '/account';
            }
            foreach ($site->enabledLocales() as $siteLocale) {
                $alternates[] = [
                    'locale' => $siteLocale->locale,
                    'url' => $accountPath . '?locale=' . rawurlencode($siteLocale->locale)
                        . '&lang=' . rawurlencode(\Nexis\I18n\Translator::normalizeUiLocale($siteLocale->locale)),
                    'hreflang' => $siteLocale->hreflang,
                    'published' => true,
                ];
            }
            $this->publicUi->rememberLocale($locale);
            $html = $this->themeViews->renderFile($template, [
                'site' => $site,
                'locale' => $locale,
                'title' => $title,
                'contentHtml' => $contentHtml,
                'alternates' => $alternates,
                'primaryNav' => $primaryNav,
                'footerNav' => $footerNav,
                'accountNav' => $this->accountNav->forSite($site, $locale, $basePath),
                'themeUi' => [
                    'ariaPrimary' => $translator->get('theme.nav.primary'),
                    'ariaLocales' => $translator->get('theme.nav.locales'),
                    'ariaFooter' => $translator->get('theme.nav.footer'),
                    'unavailable' => $translator->get('theme.locale.unavailable'),
                ],
                'layout' => $theme['layout'],
                'basePath' => $basePath,
                'homeUrl' => $this->paths->url($site, $locale, '/', $basePath, $scheme),
                'cssVariables' => $theme['cssVariables']
                    . ($theme['customCss'] !== '' ? "\n" . $theme['customCss'] : ''),
                'themeCssUrl' => $theme['themeCssUrl'],
                'logoUrl' => $theme['logoUrl'],
                'themeName' => $theme['manifest']->name,
            ]);
        } catch (Throwable) {
            $html = $this->views->render($view, array_merge($data, ['title' => $title]), 'account.layout');
        }

        return $this->responses->html($html, $status);
    }

    /**
     * @return array{0: Site, 1: string}|ResponseInterface
     */
    private function siteContext(ServerRequestInterface $request): array|ResponseInterface
    {
        $site = $this->sites->installed();
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$site instanceof Site) {
            $t = $this->translatorFor($request);

            return $this->responses->html($t->get('account.error.no_site'), 503);
        }

        return [$site, $basePath];
    }

    private function requireUser(ServerRequestInterface $request, string $basePath): User|ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user instanceof User) {
            return $user;
        }
        $current = $this->login->current();
        if ($current instanceof User) {
            return $current;
        }

        return $this->responses->redirect($basePath . '/account/login');
    }

    private function translatorFor(ServerRequestInterface $request, ?User $user = null): Translator
    {
        if ($user instanceof User) {
            return $this->translator->withLocale($this->ui->localeFor($user));
        }
        $current = $this->login->current();
        if ($current instanceof User) {
            return $this->translator->withLocale($this->ui->localeFor($current));
        }

        return $this->translator->withLocale($this->resolveGuestUiLocale($request));
    }

    private function resolveGuestUiLocale(ServerRequestInterface $request): string
    {
        $site = $this->sites->installed();

        return $this->publicUi->localeFromRequest($request, $site?->defaultLocale);
    }
}
