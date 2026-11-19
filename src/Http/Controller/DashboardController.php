<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Content\PageRepository;
use Nexis\Http\AdminContentLocale;
use Nexis\Http\ResponseFactory;
use Nexis\Http\ViewRenderer;
use Nexis\I18n\AdminUi;
use Nexis\Media\MediaRepository;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class DashboardController
{
    public function __construct(
        private ViewRenderer $views,
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private PageRepository $pages,
        private MediaRepository $media,
        private LocalePathResolver $paths,
        private AdminContentLocale $contentLocale,
        private AdminUi $ui,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$user instanceof User) {
            return $this->responses->redirect($basePath . '/admin/login');
        }

        $site = $this->sites->installed();
        if ($site === null) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_site_install'), 503);
        }
        if (!$this->policy->view($user, $site)) {
            return $this->responses->html($this->ui->get($user, 'admin.error.no_access'), 403);
        }

        $locale = $this->contentLocale->current($site, $user);
        $homeUrl = $this->paths->url($site, $locale, '/', $basePath);

        return $this->responses->html($this->views->render('admin.dashboard', [
            'user' => $user,
            'site' => $site,
            'locale' => $locale,
            'basePath' => $basePath,
            'csrf' => (string) $request->getAttribute('csrf', ''),
            'pageCount' => $this->pages->countBySite($site->id),
            'mediaCount' => $this->media->countBySite($site->id),
            'localeCount' => count($site->enabledLocales()),
            'homeUrl' => $homeUrl,
        ], 'admin.layout'));
    }
}
