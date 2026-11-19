<?php

declare(strict_types=1);

namespace Nexis\Plugins\Blog;

use Nexis\Plugin\BlogMenuSync;
use Nexis\Plugin\UninstallHandler;
use Nexis\Site\SiteId;
use Psr\Container\ContainerInterface;

final class BlogUninstallHandler implements UninstallHandler
{
    public function uninstall(SiteId $siteId, ContainerInterface $container): void
    {
        $container->get(BlogMenuSync::class)->remove($siteId);
    }
}
