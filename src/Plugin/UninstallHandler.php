<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Site\SiteId;
use Psr\Container\ContainerInterface;

/**
 * Optional site-data cleanup when a plugin is uninstalled from a site.
 */
interface UninstallHandler
{
    public function uninstall(SiteId $siteId, ContainerInterface $container): void;
}
