<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Builder\BlockPattern;
use Nexis\Builder\DocumentValidator;
use Nexis\Builder\PatternId;
use Nexis\Builder\PatternRepository;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Site\SiteRepository;
use Nexis\Support\Uuid;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PatternAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private PatternRepository $patterns,
        private DocumentValidator $validator,
        private SitePolicy $policy,
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
        $query = $request->getQueryParams();

        return $this->responses->html($this->views->render('admin.patterns.index', [
            'user' => $user,
            'site' => $site,
            'patterns' => $this->patterns->listBySite($site->id),
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'error' => is_string($query['error'] ?? null) ? (string) $query['error'] : '',
            'saved' => ($query['saved'] ?? '') === '1',
            'deleted' => ($query['deleted'] ?? '') === '1',
        ], 'admin.layout'));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $name = trim(RequestInput::string($request, 'name'));
        if ($name === '') {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_name')));
        }
        if (strlen($name) > 190) {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_name')));
        }

        try {
            $node = $this->parseDocumentNode($request, $user);
        } catch (InvalidArgumentException $e) {
            return $this->redirectAfterCreate($request, $basePath, $user, $e->getMessage());
        }

        $wrapped = ['schemaVersion' => 1, 'root' => $node];
        $errors = $this->validator->validate($wrapped);
        if ($errors !== []) {
            return $this->redirectAfterCreate($request, $basePath, $user, implode(' ', $errors));
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $pattern = new BlockPattern(
            new PatternId(Uuid::v7()),
            $site->id,
            $name,
            null,
            $node,
            $user->id->value,
            $now,
            $now,
        );
        $this->patterns->save($pattern);
        $this->audit->log('pattern.create', $site->id, $user->id, 'pattern', $pattern->id->value, ['name' => $name]);

        return $this->redirectAfterCreate($request, $basePath, $user, null, true);
    }

    public function rename(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        try {
            $id = new PatternId(RequestInput::string($request, 'pattern_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_invalid')));
        }

        $pattern = $this->patterns->findById($id, $site->id);
        if ($pattern === null) {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_not_found')));
        }

        $name = trim(RequestInput::string($request, 'name'));
        if ($name === '' || strlen($name) > 190) {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_name')));
        }

        $updated = new BlockPattern(
            $pattern->id,
            $pattern->siteId,
            $name,
            $pattern->description,
            $pattern->document,
            $pattern->createdBy,
            $pattern->createdAt,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $this->patterns->save($updated);
        $this->audit->log('pattern.rename', $site->id, $user->id, 'pattern', $pattern->id->value, ['name' => $name]);

        return $this->responses->redirect($basePath . '/admin/patterns?saved=1');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        try {
            $id = new PatternId(RequestInput::string($request, 'pattern_id'));
        } catch (InvalidArgumentException) {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_invalid')));
        }

        if (!$this->patterns->delete($id, $site->id)) {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($this->ui->get($user, 'admin.patterns.error_not_found')));
        }

        $this->audit->log('pattern.delete', $site->id, $user->id, 'pattern', $id->value);

        return $this->responses->redirect($basePath . '/admin/patterns?deleted=1');
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
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }

        return [$user, $site, $basePath];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDocumentNode(ServerRequestInterface $request, User $user): array
    {
        $raw = RequestInput::string($request, 'document');
        if ($raw === '') {
            throw new InvalidArgumentException($this->ui->get($user, 'admin.patterns.error_document'));
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException($this->ui->get($user, 'admin.patterns.error_document'));
        }
        if (!is_array($decoded)) {
            throw new InvalidArgumentException($this->ui->get($user, 'admin.patterns.error_document'));
        }

        return $decoded;
    }

    private function redirectAfterCreate(
        ServerRequestInterface $request,
        string $basePath,
        User $user,
        ?string $error,
        bool $success = false,
    ): ResponseInterface {
        $returnTo = trim(RequestInput::string($request, 'return_to'));
        if ($returnTo !== '' && str_starts_with($returnTo, $basePath . '/admin/pages/') && str_contains($returnTo, '/builder')) {
            if ($error !== null) {
                return $this->responses->redirect($returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'pattern_error=' . rawurlencode($error));
            }

            return $this->responses->redirect($returnTo . (str_contains($returnTo, '?') ? '&' : '?') . 'pattern_saved=1');
        }

        if ($error !== null) {
            return $this->responses->redirect($basePath . '/admin/patterns?error=' . rawurlencode($error));
        }
        if ($success) {
            return $this->responses->redirect($basePath . '/admin/patterns?saved=1');
        }

        return $this->responses->redirect($basePath . '/admin/patterns');
    }
}
