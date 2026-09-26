<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\SiteExporter;
use Nexis\Site\SiteImporter;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

final class ExportAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private SiteExporter $exporter,
        private SiteImporter $importer,
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
        $notice = '';
        if (RequestInput::query($request, 'exported') === '1') {
            $notice = $this->ui->get($user, 'admin.error.export_created');
        }
        if (RequestInput::query($request, 'imported') === '1') {
            $notice = $this->ui->get($user, 'admin.error.export_imported', [
                'pages' => RequestInput::query($request, 'pages', '0'),
                'media' => RequestInput::query($request, 'media', '0'),
                'menus' => RequestInput::query($request, 'menus', '0'),
                'redirects' => RequestInput::query($request, 'redirects', '0'),
                'settings' => RequestInput::query($request, 'settings', '0'),
                'published' => RequestInput::query($request, 'published', '0'),
            ]);
        }

        return $this->responses->html($this->views->render('admin.export.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'notice' => $notice,
            'error' => RequestInput::query($request, 'error') === '1'
                ? $this->ui->get($user, 'admin.error.export_failed')
                : '',
        ], 'admin.layout'));
    }

    public function download(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $path = $this->exporter->exportZip();
        $this->audit->log('site.export', $site->id, $user->id, 'site', $site->id->value, [
            'file' => basename($path),
        ]);
        $body = file_get_contents($path);
        if ($body === false) {
            return $this->responses->html('Export fehlgeschlagen.', 500);
        }

        return $this->responses->file($body, 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"');
    }

    public function upload(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $files = $request->getUploadedFiles();
        $file = $files['archive'] ?? null;
        if (!$file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return $this->responses->redirect($basePath . '/admin/export?error=1');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'nexis-import-');
        if ($tmp === false) {
            return $this->responses->redirect($basePath . '/admin/export?error=1');
        }
        $zipPath = $tmp . '.zip';
        rename($tmp, $zipPath);
        try {
            $file->moveTo($zipPath);
            $stats = $this->importer->importZip($zipPath, $user->id, $basePath);
            $this->audit->log('site.import', $site->id, $user->id, 'site', $site->id->value, $stats);

            return $this->responses->redirect($basePath . '/admin/export?imported=1'
                . '&pages=' . $stats['pages']
                . '&media=' . $stats['media']
                . '&menus=' . ($stats['menus'] ?? 0)
                . '&redirects=' . ($stats['redirects'] ?? 0)
                . '&settings=' . ($stats['settings'] ?? 0)
                . '&published=' . $stats['published']);
        } catch (Throwable) {
            return $this->responses->redirect($basePath . '/admin/export?error=1');
        } finally {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }
    }

    /**
     * @return array{User, \Nexis\Site\Site, string}|ResponseInterface
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
        if (!$this->policy->can($user, $site, Permission::EXPORT_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'export.manage']), 403);
        }

        return [$user, $site, $basePath];
    }
}
