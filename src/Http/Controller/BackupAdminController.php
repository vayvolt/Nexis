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
use Nexis\Infrastructure\Backup\BackupArchiver;
use Nexis\Infrastructure\Backup\BackupCatalog;
use Nexis\Infrastructure\Backup\BackupSchedule;
use Nexis\Infrastructure\Backup\BackupScheduleStore;
use Nexis\Infrastructure\Backup\LogicalBackup;
use Nexis\Kernel\Config;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Support\Clock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Backups unter `/admin/backups` (Recht settings.manage): Liste, „Jetzt sichern“,
 * Download als ZIP oder nur Dump, Löschen und der Planer (`backup.schedule.*`).
 */
final class BackupAdminController
{
    /** Guards against double submits and accidental dump storms. */
    private const MIN_CREATE_INTERVAL = 60;

    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private LogicalBackup $backup,
        private BackupCatalog $catalog,
        private BackupArchiver $archiver,
        private BackupScheduleStore $schedules,
        private Config $config,
        private Clock $clock,
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
        $error = '';
        $created = RequestInput::query($request, 'created');
        if ($created !== '') {
            $notice = $this->ui->get($user, 'admin.backups.created', ['stamp' => $created]);
        }
        if (RequestInput::query($request, 'deleted') === '1') {
            $notice = $this->ui->get($user, 'admin.backups.deleted');
        }
        if (RequestInput::query($request, 'saved') === '1') {
            $notice = $this->ui->get($user, 'admin.common.saved');
        }
        $errorCode = RequestInput::query($request, 'error');
        if ($errorCode !== '') {
            $error = $this->errorMessage($user, $errorCode, RequestInput::query($request, 'message'));
        }

        return $this->responses->html($this->views->render('admin.backups.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'backups' => $this->catalog->all(),
            'totalBytes' => $this->catalog->totalBytes(),
            'schedule' => $this->schedules->load($site->id),
            'intervals' => BackupSchedule::intervals(),
            'retainMin' => BackupSchedule::RETAIN_MIN,
            'retainMax' => BackupSchedule::RETAIN_MAX,
            'backupsPath' => $this->backup->backupsRoot(),
            'mysqldumpMissing' => $this->backup->resolveMysqldump() === null,
            'notice' => $notice,
            'error' => $error,
        ], 'admin.layout'));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $latest = $this->catalog->latest();
        if ($latest !== null
            && $this->clock->now()->getTimestamp() - $latest->createdAt->getTimestamp() < self::MIN_CREATE_INTERVAL
        ) {
            return $this->responses->redirect($basePath . '/admin/backups?error=too_soon');
        }

        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $includeMedia = RequestInput::string($request, 'include_media') === '1';
        try {
            $result = $this->backup->create($this->databaseConfig(), (string) $this->config->get('app.url'), $includeMedia);
        } catch (Throwable $e) {
            return $this->responses->redirect(
                $basePath . '/admin/backups?error=create_failed&message=' . rawurlencode($e->getMessage()),
            );
        }

        $this->audit->log('backup.create', $site->id, $user->id, 'backup', $result['stamp'], [
            'include_media' => $includeMedia,
        ]);

        return $this->responses->redirect($basePath . '/admin/backups?created=' . rawurlencode($result['stamp']));
    }

    public function download(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $stamp = RequestInput::string($request, 'stamp');
        if ($this->catalog->find($stamp) === null) {
            return $this->responses->redirect($basePath . '/admin/backups?error=missing');
        }

        @set_time_limit(600);
        try {
            $zipPath = $this->archiver->zip($stamp);
        } catch (Throwable $e) {
            return $this->responses->redirect(
                $basePath . '/admin/backups?error=download_failed&message=' . rawurlencode($e->getMessage()),
            );
        }

        $this->audit->log('backup.download', $site->id, $user->id, 'backup', $stamp, ['format' => 'zip']);

        return $this->responses->fileStream($zipPath, 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="nexis-backup-' . $stamp . '.zip"');
    }

    public function downloadSql(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $stamp = RequestInput::string($request, 'stamp');
        $entry = $this->catalog->find($stamp);
        if ($entry === null || !$entry->restorable()) {
            return $this->responses->redirect($basePath . '/admin/backups?error=missing');
        }

        $this->audit->log('backup.download', $site->id, $user->id, 'backup', $stamp, ['format' => 'sql']);

        return $this->responses->fileStream($entry->path . DIRECTORY_SEPARATOR . 'database.sql', 'application/sql')
            ->withHeader('Content-Disposition', 'attachment; filename="nexis-backup-' . $stamp . '.sql"');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $stamp = RequestInput::string($request, 'stamp');
        if ($this->catalog->find($stamp) === null) {
            return $this->responses->redirect($basePath . '/admin/backups?error=missing');
        }
        if (!$this->backup->delete($stamp)) {
            return $this->responses->redirect($basePath . '/admin/backups?error=delete_failed');
        }

        $this->audit->log('backup.delete', $site->id, $user->id, 'backup', $stamp);

        return $this->responses->redirect($basePath . '/admin/backups?deleted=1');
    }

    public function saveSchedule(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $retain = RequestInput::string($request, 'retain');
        $schedule = new BackupSchedule(
            enabled: RequestInput::string($request, 'enabled') === '1',
            interval: BackupSchedule::normalizeInterval(RequestInput::string($request, 'interval')),
            retain: BackupSchedule::normalizeRetain(is_numeric($retain) ? (int) $retain : BackupSchedule::RETAIN_DEFAULT),
            includeMedia: RequestInput::string($request, 'schedule_include_media') === '1',
        );
        $this->schedules->save($site->id, $schedule);

        $this->audit->log('backup.schedule.update', $site->id, $user->id, 'site', $site->id->value, [
            'enabled' => $schedule->enabled,
            'interval' => $schedule->interval,
            'retain' => $schedule->retain,
            'include_media' => $schedule->includeMedia,
        ]);

        return $this->responses->redirect($basePath . '/admin/backups?saved=1');
    }

    private function errorMessage(User $user, string $code, string $detail): string
    {
        return match ($code) {
            'too_soon' => $this->ui->get($user, 'admin.backups.error_too_soon'),
            'missing' => $this->ui->get($user, 'admin.backups.error_missing'),
            'delete_failed' => $this->ui->get($user, 'admin.backups.error_delete'),
            'download_failed' => $this->ui->get($user, 'admin.backups.error_download', ['message' => $detail]),
            default => $this->ui->get($user, 'admin.backups.error_create', ['message' => $detail]),
        };
    }

    /**
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    private function databaseConfig(): array
    {
        return [
            'host' => (string) $this->config->get('db.host'),
            'port' => (string) $this->config->get('db.port'),
            'database' => (string) $this->config->get('db.database'),
            'username' => (string) $this->config->get('db.username'),
            'password' => (string) $this->config->get('db.password'),
        ];
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
