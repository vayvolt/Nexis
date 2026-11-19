<?php

declare(strict_types=1);

namespace Nexis\Audit;

use Nexis\Auth\UserId;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use PDO;

final class AuditLogger
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(
        string $action,
        ?SiteId $siteId = null,
        ?UserId $actorId = null,
        ?string $entityType = null,
        ?string $entityId = null,
        array $context = [],
        ?string $ip = null,
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_log (site_id, actor_id, action, entity_type, entity_id, ip, context, created_at)
             VALUES (:site_id, :actor_id, :action, :entity_type, :entity_id, :ip, :context, :created_at)',
        );
        $ipBin = null;
        if (is_string($ip) && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $packed = inet_pton($ip);
            $ipBin = $packed === false ? null : $packed;
        }
        $stmt->execute([
            'site_id' => $siteId?->value,
            'actor_id' => $actorId?->value,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip' => $ipBin,
            'context' => $context === [] ? null : json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * @return list<array{
     *   id:int,
     *   action:string,
     *   entity_type:?string,
     *   entity_id:?string,
     *   actor_name:?string,
     *   ip:?string,
     *   context:?array<string, mixed>,
     *   created_at:string
     * }>
     */
    public function recent(?SiteId $siteId, int $limit = 100, ?string $action = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT a.id, a.action, a.entity_type, a.entity_id, a.ip, a.context, a.created_at,
                       u.display_name AS actor_name
                FROM audit_log a
                LEFT JOIN users u ON u.id = a.actor_id
                WHERE 1=1';
        $params = [];
        if ($siteId !== null) {
            $sql .= ' AND (a.site_id = :site_id OR a.site_id IS NULL)';
            $params['site_id'] = $siteId->value;
        }
        if ($action !== null && $action !== '') {
            $sql .= ' AND a.action = :action';
            $params['action'] = $action;
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $context = null;
            $rawContext = $row['context'] ?? null;
            if (is_string($rawContext) && $rawContext !== '') {
                try {
                    $decoded = json_decode($rawContext, true, 512, JSON_THROW_ON_ERROR);
                    $context = is_array($decoded) ? $decoded : null;
                } catch (\JsonException) {
                    $context = null;
                }
            }
            $ip = null;
            $ipRaw = $row['ip'] ?? null;
            if (is_string($ipRaw) && $ipRaw !== '') {
                $decodedIp = @inet_ntop($ipRaw);
                $ip = is_string($decodedIp) ? $decodedIp : null;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'action' => (string) $row['action'],
                'entity_type' => isset($row['entity_type']) && is_string($row['entity_type']) ? $row['entity_type'] : null,
                'entity_id' => isset($row['entity_id']) && is_string($row['entity_id']) ? $row['entity_id'] : null,
                'actor_name' => isset($row['actor_name']) && is_string($row['actor_name']) ? $row['actor_name'] : null,
                'ip' => $ip,
                'context' => $context,
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    /**
     * Deletes audit rows older than the given cutoff. Default retention: 180 days.
     */
    public function purgeOlderThan(\DateTimeImmutable $cutoff): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM audit_log WHERE created_at < :cutoff');
        $stmt->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s.v')]);

        return $stmt->rowCount();
    }

    /**
     * @return list<string>
     */
    public function distinctActions(?SiteId $siteId): array
    {
        $sql = 'SELECT DISTINCT action FROM audit_log';
        $params = [];
        if ($siteId !== null) {
            $sql .= ' WHERE site_id = :site_id OR site_id IS NULL';
            $params['site_id'] = $siteId->value;
        }
        $sql .= ' ORDER BY action ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $action) {
            if (is_string($action) && $action !== '') {
                $out[] = $action;
            }
        }

        return $out;
    }
}
