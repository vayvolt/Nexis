<?php

declare(strict_types=1);

namespace Nexis\Plugins\Catalog;

use Nexis\Plugin\CatalogMenuSync;
use Nexis\Plugin\UninstallHandler;
use Nexis\Site\SiteId;
use Psr\Container\ContainerInterface;

final class CatalogUninstallHandler implements UninstallHandler
{
    public function uninstall(SiteId $siteId, ContainerInterface $container): void
    {
        $container->get(CatalogMenuSync::class)->remove($siteId);
    }
}
