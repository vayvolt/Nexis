<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Content\PrimaryNavLinkSync;
use Nexis\Site\Site;
use Nexis\Site\SiteId;

/**
 * Keeps a "Blog" entry in the primary navigation while nexis/blog is enabled.
 */
final class BlogMenuSync
{
    public const PLUGIN_KEY = 'nexis/blog';
    public const ARCHIVE_PATH = '/blog';

    public function __construct(
        private PrimaryNavLinkSync $nav,
    ) {
    }

    public function sync(SiteId $siteId, bool $enabled): void
    {
        if ($enabled) {
            $this->ensure($siteId);
        } else {
            $this->remove($siteId);
        }
    }

    public function ensure(SiteId $siteId): void
    {
        $this->nav->ensure($siteId, self::ARCHIVE_PATH, static fn (string $_locale): string => 'Blog');
    }

    public function remove(SiteId $siteId): void
    {
        $this->nav->remove($siteId, self::ARCHIVE_PATH);
    }

    public function archiveUrl(Site $site, string $locale): string
    {
        return $this->nav->archiveUrl($site, $locale, self::ARCHIVE_PATH);
    }

    public static function looksLikeBlogUrl(string $url): bool
    {
        return PrimaryNavLinkSync::looksLikePathUrl($url, self::ARCHIVE_PATH);
    }
}
