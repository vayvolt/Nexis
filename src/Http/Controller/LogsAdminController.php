<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Infrastructure\Logging\LogFileReader;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LogsAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private LogFileReader $logs,
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
        $level = is_string($query['level'] ?? null) ? strtoupper(trim((string) $query['level'])) : '';
        if (!in_array($level, ['', 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'], true)) {
            $level = '';
        }
        $limit = isset($query['limit']) ? (int) $query['limit'] : 150;
        $limit = max(50, min(500, $limit));

        return $this->responses->html($this->views->render('admin.logs.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'entries' => $this->logs->recent($limit, $level),
            'filterLevel' => $level,
            'limit' => $limit,
            'logPath' => 'storage/logs/app.log',
            'fileSize' => $this->logs->fileSizeBytes(),
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
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }
        $can = $this->policy->can($user, $site, Permission::AUDIT_VIEW)
            || $this->policy->can($user, $site, Permission::SETTINGS_MANAGE);
        if (!$can) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'audit.view']), 403);
        }

        return [$user, $site, $basePath];
    }
}
