<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Mail\MailMessage;
use Nexis\Mail\MailPort;
use Nexis\Plugin\PluginSettings;
use Nexis\Plugin\SettingsSchemaRegistry;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SubmissionsController
{
    private PluginSettings $pluginSettings;

    public function __construct(
        private PDO $pdo,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private ViewRenderer $views,
        private ResponseFactory $responses,
        SettingsSchemaRegistry $schema,
        PdoSiteSettingsRepository $store,
        private MailPort $mail,
        private AdminUi $ui,
    ) {
        $this->pluginSettings = new PluginSettings(FormMailNotifier::PLUGIN_ID, $schema, $store);
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $locale = trim(RequestInput::query($request, 'locale'));
        $status = RequestInput::query($request, 'status', 'all');
        if (!in_array($status, ['all', 'unread', 'read'], true)) {
            $status = 'all';
        }
        $q = trim(RequestInput::query($request, 'q'));
        $viewId = trim(RequestInput::query($request, 'view'));

        $sql = 'SELECT id, locale, name, email, message, created_at, read_at
                FROM plugin_nexis_forms_submissions
                WHERE site_id = :site_id';
        $params = ['site_id' => $site->id->value];
        if ($locale !== '') {
            $sql .= ' AND locale = :locale';
            $params['locale'] = $locale;
        }
        if ($status === 'unread') {
            $sql .= ' AND read_at IS NULL';
        } elseif ($status === 'read') {
            $sql .= ' AND read_at IS NOT NULL';
        }
        if ($q !== '') {
            $sql .= ' AND (name LIKE :q OR email LIKE :q OR message LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $selected = null;
        if ($viewId !== '') {
            foreach ($rows as $row) {
                if ((string) ($row['id'] ?? '') === $viewId) {
                    $selected = $row;
                    break;
                }
            }
            if ($selected === null) {
                $selected = $this->findOne($site->id->value, $viewId);
            }
            if (is_array($selected) && ($selected['read_at'] ?? null) === null) {
                $this->setRead($site->id->value, $viewId, true);
                $selected['read_at'] = gmdate('Y-m-d H:i:s.v');
                foreach ($rows as $i => $row) {
                    if ((string) ($row['id'] ?? '') === $viewId) {
                        $rows[$i]['read_at'] = $selected['read_at'];
                    }
                }
            }
        }

        return $this->responses->html($this->views->render('admin.forms.submissions', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'submissions' => $rows,
            'selected' => $selected,
            'filterLocale' => $locale,
            'filterStatus' => $status,
            'filterQ' => $q,
            'total' => $this->count($site->id->value),
            'unread' => $this->count($site->id->value, unreadOnly: true),
            'locales' => $this->distinctLocales($site->id->value),
            'saved' => RequestInput::query($request, 'saved') === '1',
            'error' => RequestInput::query($request, 'error'),
            'notifyEmail' => (string) $this->pluginSettings->get($site->id, 'notify_email', ''),
            'mailNotice' => RequestInput::query($request, 'mail') === '1' ? $this->ui->get($user, 'admin.forms.test_mail_sent') : '',
        ], 'admin.layout'));
    }

    public function saveSettings(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $email = trim(RequestInput::string($request, 'notify_email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->responses->redirect($basePath . '/admin/forms?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_notify_email')));
        }
        $this->pluginSettings->set($site->id, 'notify_email', $email);

        return $this->responses->redirect($basePath . '/admin/forms?saved=1');
    }

    public function testMail(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $to = trim(RequestInput::string($request, 'notify_email'));
        if ($to === '') {
            $to = trim((string) $this->pluginSettings->get($site->id, 'notify_email', ''));
        }
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->responses->redirect($basePath . '/admin/forms?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_recipient')));
        }
        try {
            $this->mail->send(new MailMessage(
                [$to],
                $this->ui->get($user, 'admin.mail.test_subject'),
                $this->ui->get($user, 'admin.mail.test_body_forms', ['time' => gmdate('c')]),
                null,
                null,
                $site->id->value,
                'forms_test',
            ));
        } catch (Throwable $e) {
            return $this->responses->redirect(
                $basePath . '/admin/forms?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_test_failed', [
                    'message' => $e->getMessage(),
                ])),
            );
        }

        return $this->responses->redirect($basePath . '/admin/forms?mail=1');
    }

    public function markRead(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $id = RequestInput::string($request, 'id');
        $read = RequestInput::string($request, 'read', '1') === '1';
        if ($id === '' || !$this->setRead($site->id->value, $id, $read)) {
            return $this->responses->redirect($basePath . '/admin/forms?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_not_found')));
        }

        return $this->responses->redirect($this->returnUrl($request, $basePath, $id));
    }

    public function markAllRead(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_nexis_forms_submissions
             SET read_at = :read_at
             WHERE site_id = :site_id AND read_at IS NULL',
        );
        $stmt->execute([
            'read_at' => gmdate('Y-m-d H:i:s.v'),
            'site_id' => $site->id->value,
        ]);

        return $this->responses->redirect($basePath . '/admin/forms?saved=1');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $id = RequestInput::string($request, 'id');
        $stmt = $this->pdo->prepare(
            'DELETE FROM plugin_nexis_forms_submissions WHERE id = :id AND site_id = :site_id',
        );
        $stmt->execute([
            'id' => $id,
            'site_id' => $site->id->value,
        ]);
        if ($stmt->rowCount() < 1) {
            return $this->responses->redirect($basePath . '/admin/forms?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_not_found')));
        }

        return $this->responses->redirect($basePath . '/admin/forms?saved=1');
    }

    public function export(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $stmt = $this->pdo->prepare(
            'SELECT id, locale, name, email, message, created_at, read_at
             FROM plugin_nexis_forms_submissions
             WHERE site_id = :site_id
             ORDER BY created_at ASC',
        );
        $stmt->execute(['site_id' => $site->id->value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return $this->responses->redirect($basePath . '/admin/forms?error=' . rawurlencode($this->ui->get($user, 'admin.error.forms_export_failed')));
        }
        fputcsv($fh, ['id', 'locale', 'name', 'email', 'message', 'created_at', 'read_at']);
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            fputcsv($fh, [
                (string) ($row['id'] ?? ''),
                (string) ($row['locale'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['email'] ?? ''),
                (string) ($row['message'] ?? ''),
                (string) ($row['created_at'] ?? ''),
                (string) ($row['read_at'] ?? ''),
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        if (!is_string($csv)) {
            $csv = '';
        }

        $filename = 'forms-export-' . gmdate('Ymd-His') . '.csv';

        return $this->responses->text($csv, 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-store');
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
        if (!$this->policy->can($user, $site, 'forms.manage')) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'forms.manage']), 403);
        }

        return [$user, $site, $basePath];
    }

    private function setRead(string $siteId, string $id, bool $read): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE plugin_nexis_forms_submissions
             SET read_at = :read_at
             WHERE id = :id AND site_id = :site_id',
        );
        $stmt->execute([
            'read_at' => $read ? gmdate('Y-m-d H:i:s.v') : null,
            'id' => $id,
            'site_id' => $siteId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findOne(string $siteId, string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, locale, name, email, message, created_at, read_at
             FROM plugin_nexis_forms_submissions
             WHERE id = :id AND site_id = :site_id
             LIMIT 1',
        );
        $stmt->execute(['id' => $id, 'site_id' => $siteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function count(string $siteId, bool $unreadOnly = false): int
    {
        $sql = 'SELECT COUNT(*) FROM plugin_nexis_forms_submissions WHERE site_id = :site_id';
        if ($unreadOnly) {
            $sql .= ' AND read_at IS NULL';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['site_id' => $siteId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function distinctLocales(string $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT locale FROM plugin_nexis_forms_submissions
             WHERE site_id = :site_id ORDER BY locale ASC',
        );
        $stmt->execute(['site_id' => $siteId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $locale) {
            if (is_string($locale) && $locale !== '') {
                $out[] = $locale;
            }
        }

        return $out;
    }

    private function returnUrl(ServerRequestInterface $request, string $basePath, string $id): string
    {
        $referer = $request->getHeaderLine('Referer');
        if ($referer !== '' && str_contains($referer, '/admin/forms')) {
            return $referer;
        }

        return $basePath . '/admin/forms?view=' . rawurlencode($id);
    }
}
