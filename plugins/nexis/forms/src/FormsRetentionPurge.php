<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Site\SiteId;
use PDO;

/**
 * Deletes form submissions older than the retention window (default 365 days).
 */
final class FormsRetentionPurge
{
    public const DEFAULT_DAYS = 365;

    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function purgeOlderThanDays(int $days = self::DEFAULT_DAYS): int
    {
        $days = max(30, $days);
        $cutoff = gmdate('Y-m-d H:i:s.v', time() - ($days * 86400));
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM plugin_nexis_forms_submissions WHERE created_at < :cutoff',
            );
            $stmt->execute(['cutoff' => $cutoff]);

            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function purgeForSite(SiteId $siteId): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM plugin_nexis_forms_submissions WHERE site_id = :site_id',
            );
            $stmt->execute(['site_id' => $siteId->value]);

            return $stmt->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
