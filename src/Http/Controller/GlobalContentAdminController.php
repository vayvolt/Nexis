<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Audit\AuditLogger;
use Nexis\Auth\Permission;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Cache\PageCache;
use Nexis\Content\GlobalContent;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\I18n\LocalizedMap;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class GlobalContentAdminController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private GlobalContent $globals,
        private SitePolicy $policy,
        private AuditLogger $audit,
        private PageCache $cache,
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
        $maps = $this->globals->loadMaps($site->id);

        return $this->responses->html($this->views->render('admin.globals.index', [
            'user' => $user,
            'site' => $site,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'headerCtaLabel' => $maps['headerCtaLabel'],
            'headerCtaHref' => $maps['headerCtaHref'],
            'footerTeaser' => $maps['footerTeaser'],
            'saved' => ($query['saved'] ?? '') === '1',
            'canManage' => $this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT),
        ], 'admin.layout'));
    }

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $ctx = $this->context($request);
        if ($ctx instanceof ResponseInterface) {
            return $ctx;
        }
        [$user, $site, $basePath] = $ctx;

        $locales = array_map(
            static fn ($locale): string => $locale->locale,
            $site->enabledLocales(),
        );
        $body = $request->getParsedBody();
        $posted = is_array($body) ? $body : [];

        $labelPosted = is_array($posted['header_cta_label'] ?? null) ? $posted['header_cta_label'] : [];
        $hrefPosted = is_array($posted['header_cta_href'] ?? null) ? $posted['header_cta_href'] : [];
        $teaserPosted = is_array($posted['footer_teaser'] ?? null) ? $posted['footer_teaser'] : [];

        $this->globals->saveMaps(
            $site->id,
            LocalizedMap::fromPosted($labelPosted, $locales),
            LocalizedMap::fromPosted($hrefPosted, $locales),
            LocalizedMap::fromPosted($teaserPosted, $locales),
        );
        $this->cache->invalidateSite($site->id);
        $this->audit->log('content.globals.update', $site->id, $user->id, 'site', $site->id->value);

        return $this->responses->redirect($basePath . '/admin/globals?saved=1');
    }

    /**
     * @return array{0: User, 1: Site, 2: string}|ResponseInterface
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
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site'), 500);
        }
        if (!$this->policy->can($user, $site, Permission::CONTENT_PAGE_EDIT)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access_permission', ['permission' => 'content.page.edit']), 403);
        }

        return [$user, $site, $basePath];
    }
}
