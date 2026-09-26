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
use Nexis\Site\SiteRepository;
use Nexis\Webhook\WebhookEventRegistry;
use Nexis\Webhook\WebhookRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class WebhookAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private WebhookRepository $webhooks,
        private AuditLogger $audit,
        private AdminUi $ui,
        private WebhookEventRegistry $webhookEvents,
    ) {
    }

    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        return $this->responses->html($this->views->render('admin.webhooks.index', [
            'user' => $user,
            'site' => $site,
            'endpoints' => $this->webhooks->forSite($site->id),
            'deliveries' => $this->webhooks->recentDeliveries($site->id, 40),
            'webhookEvents' => $this->webhookEvents->all(),
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'notice' => RequestInput::query($request, 'saved') === '1' ? $this->ui->get($user, 'admin.common.saved') : '',
        ], 'admin.layout'));
    }

    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $url = RequestInput::string($request, 'url');
        if (!filter_var($url, FILTER_VALIDATE_URL)
            || (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://'))
        ) {
            return $this->responses->redirect($basePath . '/admin/webhooks');
        }
        $allowed = array_fill_keys($this->webhookEvents->all(), true);
        $events = [];
        foreach (array_keys($allowed) as $event) {
            if (RequestInput::string($request, 'event_' . str_replace('.', '_', $event)) === '1') {
                $events[] = $event;
            }
        }
        if ($events === []) {
            $events = ['page.published'];
        }
        $secret = bin2hex(random_bytes(24));
        $endpoint = $this->webhooks->create($site->id, $url, $secret, $events);
        $this->audit->log('webhook.create', $site->id, $user->id, 'webhook', $endpoint->id, ['url' => $url]);

        return $this->responses->redirect($basePath . '/admin/webhooks?saved=1');
    }

    public function delete(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;
        $id = RequestInput::string($request, 'id');
        $endpoint = $this->webhooks->find($id);
        if ($endpoint !== null && $endpoint->siteId->equals($site->id)) {
            $this->webhooks->delete($id);
            $this->audit->log('webhook.delete', $site->id, $user->id, 'webhook', $id);
        }

        return $this->responses->redirect($basePath . '/admin/webhooks?saved=1');
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
        if (!$this->policy->can($user, $site, Permission::WEBHOOKS_MANAGE)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'webhooks.manage']), 403);
        }

        return [$user, $site, $basePath];
    }
}
