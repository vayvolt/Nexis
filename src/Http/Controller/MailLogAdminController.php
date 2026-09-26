<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Kernel\Config;
use Nexis\Mail\MailLogRepository;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MailLogAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private MailLogRepository $mailLog,
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

        $query = $request->getQueryParams();
        $status = is_string($query['status'] ?? null) ? trim((string) $query['status']) : '';
        if (!in_array($status, ['sent', 'failed'], true)) {
            $status = '';
        }

        return $this->responses->html($this->views->render('admin.mail.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'entries' => $this->mailLog->recent(150, $status !== '' ? $status : null),
            'filterStatus' => $status,
            'transport' => (string) $this->config->get('mail.transport', 'log'),
            'mailHost' => (string) $this->config->get('mail.host', ''),
            'mailPort' => (string) $this->config->get('mail.port', ''),
            'mailEncryption' => (string) $this->config->get('mail.encryption', ''),
            'mailFrom' => (string) $this->config->get('mail.from_address', ''),
            'mailFromName' => (string) $this->config->get('mail.from_name', ''),
            'mailUserSet' => trim((string) $this->config->get('mail.username', '')) !== '',
        ], 'admin.layout'));
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $gate = $this->authorize($request);
        if ($gate instanceof ResponseInterface) {
            return $gate;
        }
        [$user, $site, $basePath] = $gate;

        $id = (string) $request->getAttribute('id', '');
        $entry = $id !== '' ? $this->mailLog->find($id) : null;
        if ($entry === null) {
            return $this->responses->html('Mail-Eintrag nicht gefunden.', 404);
        }

        return $this->responses->html($this->views->render('admin.mail.show', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'entry' => $entry,
        ], 'admin.layout'));
    }

    /**
     * @return ResponseInterface|array{0: User, 1: \Nexis\Site\Site, 2: string}
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
        if (!$this->policy->can($user, $site, Permission::SETTINGS_MANAGE)
            && !$this->policy->can($user, $site, Permission::AUDIT_VIEW)
        ) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        return [$user, $site, $basePath];
    }
}
