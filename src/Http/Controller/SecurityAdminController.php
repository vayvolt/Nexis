<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Api\ApiTokenStore;
use Nexis\Audit\AuditLogger;
use Nexis\Auth\AuthSettings;
use Nexis\Auth\Permission;
use Nexis\Auth\PdoUserInviteRepository;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Infrastructure\Backup\BackupCatalog;
use Nexis\Kernel\Config;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Support\AdminDateTime;
use Nexis\Webhook\WebhookRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Site security overview under `/admin/security`.
 * Personal TOTP/passkeys live on `/account#security`.
 */
final class SecurityAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private AuthSettings $authSettings,
        private PdoUserInviteRepository $invites,
        private AuditLogger $audit,
        private ApiTokenStore $tokens,
        private WebhookRepository $webhooks,
        private BackupCatalog $backups,
        private Config $config,
        private AdminUi $ui,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site, $basePath] = $gate;

        $canUsers = $this->policy->can($user, $site, Permission::USERS_MANAGE);
        $canAudit = $this->policy->can($user, $site, Permission::AUDIT_VIEW);
        $canSettings = $this->policy->can($user, $site, Permission::SETTINGS_MANAGE);
        $canWebhooks = $this->policy->can($user, $site, Permission::WEBHOOKS_MANAGE);

        $ssoReady = $this->authSettings->oidcEnabled($site->id);
        $ssoToggle = $this->authSettings->oidcConfigured($site->id);
        $registrationEnabled = $this->authSettings->registrationEnabled($site->id);
        $passwordResetEnabled = $this->authSettings->passwordResetEnabled($site->id);

        $pendingInvites = $canUsers ? count($this->invites->listOpen($site->id)) : null;

        $loginFailures = [];
        if ($canAudit) {
            foreach ($this->audit->recent($site->id, 8, 'auth.login.failed') as $entry) {
                $email = '';
                if (is_array($entry['context'] ?? null) && isset($entry['context']['email'])) {
                    $email = trim((string) $entry['context']['email']);
                }
                $loginFailures[] = [
                    'when' => AdminDateTime::format($entry['created_at'], $user->uiLocale),
                    'email' => $email,
                    'ip' => (string) ($entry['ip'] ?? ''),
                ];
            }
        }

        $activeTokens = null;
        $webhookCount = null;
        $lastBackupLabel = null;
        if ($canSettings) {
            $activeTokens = 0;
            foreach ($this->tokens->listForSite($site->id) as $token) {
                if (!$token->isRevoked()) {
                    $activeTokens++;
                }
            }
            $latest = $this->backups->latest();
            $lastBackupLabel = $latest !== null
                ? AdminDateTime::format($latest->createdAt, $user->uiLocale)
                : '';
        }
        if ($canWebhooks) {
            $webhookCount = count($this->webhooks->forSite($site->id));
        }

        return $this->responses->html($this->views->render('admin.security.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'canUsers' => $canUsers,
            'canAudit' => $canAudit,
            'canSettings' => $canSettings,
            'canWebhooks' => $canWebhooks,
            'ssoReady' => $ssoReady,
            'ssoToggle' => $ssoToggle,
            'registrationEnabled' => $registrationEnabled,
            'passwordResetEnabled' => $passwordResetEnabled,
            'pendingInvites' => $pendingInvites,
            'loginFailures' => $loginFailures,
            'activeTokens' => $activeTokens,
            'webhookCount' => $webhookCount,
            'lastBackupLabel' => $lastBackupLabel,
            'appDebug' => $this->config->debug(),
            'appEnv' => $this->config->envName(),
        ], 'admin.layout'));
    }

    /**
     * @return ResponseInterface|array{0: User, 1: Site, 2: string}
     */
    private function authorize(ServerRequestInterface $request): ResponseInterface|array
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
        if (!$this->policy->canAccessCms($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        return [$user, $site, $basePath];
    }
}
