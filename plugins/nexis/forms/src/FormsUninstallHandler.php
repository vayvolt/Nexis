<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Plugin\UninstallHandler;
use Nexis\Site\SiteId;
use PDO;
use Psr\Container\ContainerInterface;

final class FormsUninstallHandler implements UninstallHandler
{
    public function uninstall(SiteId $siteId, ContainerInterface $container): void
    {
        $pdo = $container->get(PDO::class);
        foreach ([
            'DELETE FROM plugin_nexis_forms_submissions WHERE site_id = :site_id',
            'DELETE FROM plugin_nexis_forms_definitions WHERE site_id = :site_id',
        ] as $sql) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['site_id' => $siteId->value]);
            } catch (\Throwable) {
                // Table may be missing if migrations never ran.
            }
        }
        // plugin:nexis/forms.* site_settings are wiped by core PluginUninstaller.
    }
}
