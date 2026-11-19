<?php

declare(strict_types=1);

namespace Nexis\Plugins\Blog;

use Nexis\Builder\Core\AbstractCoreBlock;
use Nexis\Content\PageRepository;
use Nexis\Content\PageType;
use Nexis\Content\PublishMetaHtml;
use Nexis\I18n\PublicUi;
use Nexis\Site\LocalePathResolver;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;

final class BlogPostsBlock extends AbstractCoreBlock
{
    public function __construct(
        private PageRepository $pages,
        private SiteRepository $sites,
        private LocalePathResolver $paths,
        private PublicUi $ui,
    ) {
    }

    public function type(): string
    {
        return 'nexis/blog/posts';
    }

    public function label(): string
    {
        return 'Blog-Beiträge';
    }

    public function defaultProps(): array
    {
        return [
            'heading' => 'Aktuelles',
            'limit' => 5,
            'emptyText' => 'Noch keine Beiträge.',
        ];
    }

    public function propsSchema(): object
    {
        return (object) [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => (object) [
                'heading' => (object) ['type' => 'string'],
                'limit' => (object) ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                'emptyText' => (object) ['type' => 'string'],
            ],
        ];
    }

    public function render(array $block, array $context): string
    {
        $props = $this->props($block);
        $locale = (string) ($context['locale'] ?? 'de');
        $heading = (string) ($props['heading'] ?? '');
        if ($heading === '' || $heading === 'Aktuelles') {
            $heading = $this->ui->get($locale, 'public.blog.heading_default');
        }
        $limit = (int) ($props['limit'] ?? 5);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        $empty = (string) ($props['emptyText'] ?? '');
        if ($empty === '' || $empty === 'Noch keine Beiträge.') {
            $empty = $this->ui->get($locale, 'public.blog.empty_block');
        }
        $basePath = (string) ($context['basePath'] ?? '');
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $site = $context['site'] ?? null;
        if (!$site instanceof Site) {
            $site = $this->sites->installed();
        }
        if (!$site instanceof Site) {
            return '';
        }

        $posts = $this->pages->listPublishedByTypeForLocale($site->id, $locale, PageType::POST);
        $posts = array_slice($posts, 0, $limit);

        $html = '<section class="nx-blog-posts">';
        if ($heading !== '') {
            $html .= '<h2>' . $e($heading) . '</h2>';
        }
        if ($posts === []) {
            $html .= '<p class="nx-blog-posts__empty">' . $e($empty) . '</p>';
        } else {
            $html .= '<ul class="nx-blog-posts__list">';
            foreach ($posts as $post) {
                $url = $this->paths->url($site, $post->locale, $post->path, $basePath);
                $html .= '<li><a href="' . $e($url) . '">' . $e($post->title) . '</a>';
                $html .= PublishMetaHtml::renderHtml(
                    $post,
                    $locale,
                    fn (string $name): string => $this->ui->get($locale, 'public.blog.by', ['name' => $name]),
                );
                $excerpt = $post->documentDescription;
                if ($excerpt !== '') {
                    $html .= '<p>' . $e($excerpt) . '</p>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        $archive = $this->paths->url($site, $locale, BlogPaths::archivePath(), $basePath);
        $html .= '<p class="nx-blog-posts__more"><a href="' . $e($archive) . '">'
            . $e($this->ui->get($locale, 'public.blog.more')) . '</a></p>';
        $html .= '</section>';

        return $html;
    }
}
