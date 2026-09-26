<?php

declare(strict_types=1);

namespace Nexis\Plugins\Redirects;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RedirectsAdminController
{
    public function __construct(
        private PDO $pdo,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private ViewRenderer $views,
        private ResponseFactory $responses,
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

        $stmt = $this->pdo->prepare(
            'SELECT id, locale, from_path, to_url, status_code, created_at
             FROM plugin_nexis_redirects
             WHERE site_id = :site_id
             ORDER BY from_path ASC',
        );
        $stmt->execute(['site_id' => $site->id->value]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notFound = [];
        try {
            $hits = $this->pdo->prepare(
                'SELECT id, locale, path, hit_count, last_referer, first_seen_at, last_seen_at
                 FROM plugin_nexis_not_found_hits
                 WHERE site_id = :site_id
                 ORDER BY hit_count DESC, last_seen_at DESC
                 LIMIT 100',
            );
            $hits->execute(['site_id' => $site->id->value]);
            /** @var list<array<string, mixed>> $notFound */
            $notFound = $hits->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $notFound = [];
        }

        $edit = null;
        $editId = trim(RequestInput::query($request, 'edit'));
        if ($editId !== '') {
            foreach ($rows as $row) {
                if ((string) ($row['id'] ?? '') === $editId) {
                    $edit = $row;
                    break;
                }
            }
        }

        return $this->responses->html($this->views->render('admin.redirects.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'redirects' => $rows,
            'notFoundHits' => $notFound,
            'edit' => $edit,
            'error' => RequestInput::query($request, 'error'),
            'saved' => RequestInput::query($request, 'saved') === '1',
            'imported' => RequestInput::query($request, 'imported'),
            'skipped' => RequestInput::query($request, 'skipped'),
            'showImport' => RequestInput::query($request, 'import') === '1'
                || RequestInput::query($request, 'imported') !== '',
        ], 'admin.layout'));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return $this->save($request, null);
    }

    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $id = trim(RequestInput::string($request, 'id'));
        if ($id === '') {
            $ctx = $this->context($request);
            if ($ctx instanceof ResponseInterface) {
                return $ctx;
            }
            [, , $basePath] = $ctx;

            return $this->responses->redirect($basePath . '/admin/redirects');
        }

        return $this->save($request, $id);
    }

    private function save(ServerRequestInterface $request, ?string $id): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $from = RedirectCsv::normalizeFrom(RequestInput::string($request, 'from_path'));
        $to = trim(RequestInput::string($request, 'to_url'));
        $locale = trim(RequestInput::string($request, 'locale'));
        $code = (int) RequestInput::string($request, 'status_code', '301');
        if (!in_array($code, [301, 302, 307, 308], true)) {
            $code = 301;
        }
        $editQuery = $id !== null ? '?edit=' . rawurlencode($id) . '&' : '?';
        if ($from === '' || $to === '') {
            return $this->responses->redirect(
                $basePath . '/admin/redirects' . $editQuery . 'error=' . rawurlencode($this->ui->get($user, 'admin.error.redirects_required')),
            );
        }

        try {
            if ($id !== null) {
                $this->updateRedirect(
                    $site->id->value,
                    $id,
                    $from,
                    $to,
                    $locale !== '' ? $locale : null,
                    $code,
                );
            } else {
                $this->upsertRedirect(
                    $site->id->value,
                    $from,
                    $to,
                    $locale !== '' ? $locale : null,
                    $code,
                );
                $hitId = trim(RequestInput::string($request, 'not_found_id'));
                if ($hitId !== '') {
                    $del = $this->pdo->prepare(
                        'DELETE FROM plugin_nexis_not_found_hits WHERE id = :id AND site_id = :site_id',
                    );
                    $del->execute(['id' => $hitId, 'site_id' => $site->id->value]);
                }
            }
        } catch (\Throwable) {
            return $this->responses->redirect(
                $basePath . '/admin/redirects' . $editQuery . 'error=' . rawurlencode($this->ui->get($user, 'admin.error.redirects_save_failed')),
            );
        }

        return $this->responses->redirect($basePath . '/admin/redirects?saved=1');
    }

    public function importCsv(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $files = $request->getUploadedFiles();
        $file = $files['csv'] ?? null;
        if (!$file instanceof \Psr\Http\Message\UploadedFileInterface || $file->getError() !== \UPLOAD_ERR_OK) {
            return $this->responses->redirect($basePath . '/admin/redirects?import=1&error=' . rawurlencode($this->ui->get($user, 'admin.redirects.import_error_file')));
        }
        $size = $file->getSize() ?? 0;
        if ($size > 2_000_000) {
            return $this->responses->redirect($basePath . '/admin/redirects?import=1&error=' . rawurlencode($this->ui->get($user, 'admin.redirects.import_error_size')));
        }

        try {
            $raw = (string) $file->getStream()->getContents();
        } catch (\Throwable) {
            return $this->responses->redirect($basePath . '/admin/redirects?import=1&error=' . rawurlencode($this->ui->get($user, 'admin.redirects.import_error_file')));
        }

        $rows = RedirectCsv::parse($raw);
        if ($rows === []) {
            return $this->responses->redirect($basePath . '/admin/redirects?import=1&error=' . rawurlencode($this->ui->get($user, 'admin.redirects.import_error_empty')));
        }

        $imported = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            try {
                $this->upsertRedirect(
                    $site->id->value,
                    $row['from_path'],
                    $row['to_url'],
                    $row['locale'],
                    $row['status_code'],
                );
                $imported++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return $this->responses->redirect(
            $basePath . '/admin/redirects?imported=' . $imported . '&skipped=' . $skipped,
        );
    }

    public function exportCsv(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $stmt = $this->pdo->prepare(
            'SELECT from_path, to_url, locale, status_code
             FROM plugin_nexis_redirects
             WHERE site_id = :site_id
             ORDER BY from_path ASC',
        );
        $stmt->execute(['site_id' => $site->id->value]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $csv = RedirectCsv::export($rows);

        return $this->responses->text($csv, 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="redirects.csv"')
            ->withHeader('Cache-Control', 'no-store');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $id = RequestInput::string($request, 'id');
        if ($id !== '') {
            $stmt = $this->pdo->prepare(
                'DELETE FROM plugin_nexis_redirects WHERE id = :id AND site_id = :site_id',
            );
            $stmt->execute(['id' => $id, 'site_id' => $site->id->value]);
        }

        return $this->responses->redirect($basePath . '/admin/redirects?saved=1');
    }

    public function deleteNotFound(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $id = RequestInput::string($request, 'id');
        if ($id !== '') {
            $stmt = $this->pdo->prepare(
                'DELETE FROM plugin_nexis_not_found_hits WHERE id = :id AND site_id = :site_id',
            );
            $stmt->execute(['id' => $id, 'site_id' => $site->id->value]);
        }

        return $this->responses->redirect($basePath . '/admin/redirects?saved=1');
    }

    public function clearNotFound(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $stmt = $this->pdo->prepare('DELETE FROM plugin_nexis_not_found_hits WHERE site_id = :site_id');
        $stmt->execute(['site_id' => $site->id->value]);

        return $this->responses->redirect($basePath . '/admin/redirects?saved=1');
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
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        return [$user, $site, $basePath];
    }

    private function upsertRedirect(
        string $siteId,
        string $from,
        string $to,
        ?string $locale,
        int $code,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_nexis_redirects
                (id, site_id, locale, from_path, to_url, status_code, created_at)
             VALUES (:id, :site_id, :locale, :from_path, :to_url, :status_code, :created_at)
             ON DUPLICATE KEY UPDATE
                to_url = VALUES(to_url),
                status_code = VALUES(status_code)',
        );
        $stmt->execute([
            'id' => Uuid::v7(),
            'site_id' => $siteId,
            'locale' => $locale,
            'from_path' => $from,
            'to_url' => $to,
            'status_code' => $code,
            'created_at' => gmdate('Y-m-d H:i:s.v'),
        ]);
    }

    private function updateRedirect(
        string $siteId,
        string $id,
        string $from,
        string $to,
        ?string $locale,
        int $code,
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_nexis_redirects
             SET locale = :locale, from_path = :from_path, to_url = :to_url, status_code = :status_code
             WHERE id = :id AND site_id = :site_id',
        );
        $stmt->execute([
            'id' => $id,
            'site_id' => $siteId,
            'locale' => $locale,
            'from_path' => $from,
            'to_url' => $to,
            'status_code' => $code,
        ]);
        if ($stmt->rowCount() === 0) {
            // No matching row, or values unchanged — verify ownership.
            $check = $this->pdo->prepare(
                'SELECT 1 FROM plugin_nexis_redirects WHERE id = :id AND site_id = :site_id LIMIT 1',
            );
            $check->execute(['id' => $id, 'site_id' => $siteId]);
            if ($check->fetchColumn() === false) {
                throw new \RuntimeException('Redirect not found');
            }
        }
    }
}
