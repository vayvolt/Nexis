<?php

declare(strict_types=1);

namespace Nexis\Plugins\Redirects;

use Nexis\Plugin\UninstallHandler;
use Nexis\Site\SiteId;
use PDO;
use Psr\Container\ContainerInterface;

final class RedirectsUninstallHandler implements UninstallHandler
{
    public function uninstall(SiteId $siteId, ContainerInterface $container): void
    {
        $pdo = $container->get(PDO::class);
        foreach (['plugin_nexis_redirects', 'plugin_nexis_not_found_hits'] as $table) {
            try {
                $stmt = $pdo->prepare('DELETE FROM ' . $table . ' WHERE site_id = :site_id');
                $stmt->execute(['site_id' => $siteId->value]);
            } catch (\Throwable) {
                // Table may be missing if migrations never ran.
            }
        }
    }
}
