<?php

declare(strict_types=1);

namespace Nexis\Http\Controller\Api;

use Nexis\Api\ApiGuard;
use Nexis\Api\ApiSerializer;
use Nexis\Builder\SnapshotRepository;
use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageRepository;
use Nexis\Http\RequestInput;
use Nexis\Site\Site;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Read-only public API (`/api/v1/public`). No authentication, published content only.
 */
final class PublicApiController
{
    public function __construct(
        private ApiGuard $guard,
        private PageRepository $pages,
        private SnapshotRepository $snapshots,
    ) {
    }

    public function sitesCurrent(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->guard->site($request);
        if ($site instanceof ResponseInterface) {
            return $site;
        }

        return $this->guard->ok($request, ApiSerializer::site($site));
    }

    public function pagesIndex(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->guard->site($request);
        if ($site instanceof ResponseInterface) {
            return $site;
        }
        $locale = $this->locale($request, $site);
        if ($locale === null) {
            return $this->guard->invalid($request, 'api.error.locale_unknown');
        }

        $type = RequestInput::query($request, 'type');
        $pages = $type !== ''
            ? $this->pages->listPublishedByTypeForLocale($site->id, $locale, $type)
            : $this->pages->listPublishedForLocale($site->id, $locale);

        return $this->guard->okList(
            $request,
            array_map(static fn (Page $page): array => ApiSerializer::page($page), $pages),
            ['locale' => $locale, 'count' => count($pages)],
        );
    }

    /**
     * `GET /api/v1/public/pages/by-path?path=/team/jobs&locale=de` — avoids
     * matching multi-segment page paths in the route pattern.
     */
    public function pagesByPath(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->guard->site($request);
        if ($site instanceof ResponseInterface) {
            return $site;
        }
        $locale = $this->locale($request, $site);
        if ($locale === null) {
            return $this->guard->invalid($request, 'api.error.locale_unknown');
        }
        $path = RequestInput::query($request, 'path');
        if (trim($path) === '') {
            return $this->guard->invalid($request, 'api.error.path_required');
        }

        $page = $this->pages->findByPath($site->id, $locale, '/' . trim($path, '/'));
        if ($page === null || !$this->isPublic($page)) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        return $this->guard->ok($request, $this->detail($page));
    }

    public function pagesShow(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->guard->site($request);
        if ($site instanceof ResponseInterface) {
            return $site;
        }
        $page = $this->findById($request, $site);
        if ($page === null || !$this->isPublic($page)) {
            return $this->guard->notFound($request, 'page.not_found');
        }

        return $this->guard->ok($request, $this->detail($page));
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Page $page): array
    {
        $document = null;
        if ($page->publishedSnapshotId !== null) {
            $snapshot = $this->snapshots->findById($page->publishedSnapshotId);
            if ($snapshot !== null) {
                $document = $snapshot->payload;
            }
        }

        $alternates = [];
        foreach ($this->pages->alternates($page) as $alternate) {
            if ($this->isPublic($alternate)) {
                $alternates[] = ApiSerializer::alternate($alternate);
            }
        }

        return ApiSerializer::page($page) + [
            'metadata' => ApiSerializer::metadata($page),
            'document' => $document,
            'alternates' => $alternates,
        ];
    }

    private function findById(ServerRequestInterface $request, Site $site): ?Page
    {
        $id = (string) $request->getAttribute('id', '');
        try {
            $page = $this->pages->findById(new PageId($id));
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $page !== null && $page->siteId->equals($site->id) ? $page : null;
    }

    private function isPublic(Page $page): bool
    {
        return $page->isPublished && $page->publishedSnapshotId !== null;
    }

    private function locale(ServerRequestInterface $request, Site $site): ?string
    {
        $requested = trim(RequestInput::query($request, 'locale'));
        if ($requested === '') {
            return $site->defaultSiteLocale()->locale;
        }

        return $site->locale($requested)?->locale;
    }
}
