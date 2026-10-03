<?php

declare(strict_types=1);

namespace Nexis\Http\Controller\Api;

use Nexis\Api\ApiTokenStore;
use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTML admin UI for API tokens (`/admin/api-tokens`, permission settings.manage).
 */
final class ApiTokenAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private ApiTokenStore $tokens,
        private AuditLogger $audit,
        private AdminUi $ui,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        return $this->responses->html($this->render($request, $user, $site, $basePath));
    }

    /**
     * The plaintext secret is shown once in the response of this POST; it is
     * never stored and never put into a redirect URL.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $name = RequestInput::string($request, 'name');
        if ($name === '') {
            return $this->responses->html(
                $this->render($request, $user, $site, $basePath, error: $this->ui->get($user, 'admin.api_tokens.error_name')),
                422,
            );
        }
        $name = mb_substr($name, 0, 120);

        $scopes = [];
        foreach (Permission::core() as $permission) {
            if (RequestInput::string($request, 'scope_' . str_replace('.', '_', $permission)) === '1') {
                $scopes[] = $permission;
            }
        }

        $created = $this->tokens->create($site->id, $user->id, $name, $scopes);
        $this->audit->log(
            'api_token.create',
            $site->id,
            $user->id,
            'api_token',
            $created->token->id,
            ['name' => $name, 'scopes' => $scopes],
        );

        return $this->responses->html($this->render(
            $request,
            $user,
            $site,
            $basePath,
            notice: $this->ui->get($user, 'admin.api_tokens.created'),
            plaintext: $created->plaintext,
        ));
    }

    public function revoke(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $id = RequestInput::string($request, 'id');
        $token = $this->tokens->find($id, $site->id);
        if ($token !== null && $this->tokens->revoke($token->id, $site->id)) {
            $this->audit->log('api_token.revoke', $site->id, $user->id, 'api_token', $token->id);
        }

        return $this->responses->redirect($basePath . '/admin/api-tokens?revoked=1');
    }

    private function render(
        ServerRequestInterface $request,
        User $user,
        Site $site,
        string $basePath,
        string $notice = '',
        string $error = '',
        string $plaintext = '',
    ): string {
        if ($notice === '' && RequestInput::query($request, 'revoked') === '1') {
            $notice = $this->ui->get($user, 'admin.api_tokens.revoked');
        }

        return $this->views->render('admin.api-tokens.index', [
            'user' => $user,
            'site' => $site,
            'tokens' => $this->tokens->listForSite($site->id),
            'scopeOptions' => Permission::core(),
            'plaintext' => $plaintext,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'notice' => $notice,
            'error' => $error,
        ], 'admin.layout');
    }

    /**
     * @return array{User, Site, string}|ResponseInterface
     */
    private function context(ServerRequestInterface $request): array|ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }
        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site'), 503);
        }
        if (!$this->policy->can($user, $site, Permission::SETTINGS_MANAGE)) {
            return $this->responses->html(
                $this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'settings.manage']),
                403,
            );
        }

        return [$user, $site, $basePath];
    }
}
