<?php

declare(strict_types=1);

namespace Nexis\Webhook;

use Nexis\Security\SecretBox;
use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;

final class WebhookRepository
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
        private SecretBox $secrets,
    ) {
    }

    /**
     * @return list<WebhookEndpoint>
     */
    public function forSite(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM webhook_endpoints WHERE site_id = :site_id ORDER BY created_at ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $out[] = $this->map($row);
            }
        }

        return $out;
    }

    /**
     * @return list<WebhookEndpoint>
     */
    public function activeForEvent(SiteId $siteId, string $event): array
    {
        $out = [];
        foreach ($this->forSite($siteId) as $endpoint) {
            if ($endpoint->isActive && in_array($event, $endpoint->events, true)) {
                $out[] = $endpoint;
            }
        }

        return $out;
    }

    public function find(string $id): ?WebhookEndpoint
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webhook_endpoints WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    /**
     * @param list<string> $events
     */
    public function create(SiteId $siteId, string $url, string $secret, array $events): WebhookEndpoint
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $endpoint = new WebhookEndpoint(
            Uuid::v7(),
            $siteId,
            $url,
            $secret,
            array_values($events),
            true,
        );
        $stmt = $this->pdo->prepare(
            'INSERT INTO webhook_endpoints (id, site_id, url, secret, events, is_active, created_at, updated_at)
             VALUES (:id, :site_id, :url, :secret, :events, 1, :created_at, :updated_at)',
        );
        $stmt->execute([
            'id' => $endpoint->id,
            'site_id' => $siteId->value,
            'url' => $url,
            'secret' => $this->encodeSecret($secret),
            'events' => json_encode($endpoint->events, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $endpoint;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM webhook_endpoints WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function logDelivery(
        string $endpointId,
        SiteId $siteId,
        string $eventName,
        string $status,
        ?int $httpStatus = null,
        ?string $error = null,
        int $attempts = 1,
    ): void {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO webhook_deliveries
                    (endpoint_id, site_id, event_name, status, http_status, error, attempts, created_at)
                 VALUES
                    (:endpoint_id, :site_id, :event_name, :status, :http_status, :error, :attempts, :created_at)',
            );
            $stmt->execute([
                'endpoint_id' => $endpointId,
                'site_id' => $siteId->value,
                'event_name' => $eventName,
                'status' => $status,
                'http_status' => $httpStatus,
                'error' => $error !== null ? mb_substr($error, 0, 500) : null,
                'attempts' => $attempts,
                'created_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
            ]);
        } catch (\Throwable) {
            // Table may be missing before migrate; delivery must not fail.
        }
    }

    /**
     * @return list<array{
     *   id: int,
     *   endpoint_id: string,
     *   url: ?string,
     *   event_name: string,
     *   status: string,
     *   http_status: ?int,
     *   error: ?string,
     *   attempts: int,
     *   created_at: string
     * }>
     */
    public function recentDeliveries(SiteId $siteId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        try {
            $stmt = $this->pdo->prepare(
                'SELECT d.id, d.endpoint_id, e.url, d.event_name, d.status, d.http_status, d.error, d.attempts, d.created_at
                 FROM webhook_deliveries d
                 LEFT JOIN webhook_endpoints e ON e.id = d.endpoint_id
                 WHERE d.site_id = :site_id
                 ORDER BY d.created_at DESC
                 LIMIT ' . $limit,
            );
            $stmt->execute(['site_id' => $siteId->value]);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'id' => (int) $row['id'],
                'endpoint_id' => (string) $row['endpoint_id'],
                'url' => is_string($row['url'] ?? null) ? (string) $row['url'] : null,
                'event_name' => (string) $row['event_name'],
                'status' => (string) $row['status'],
                'http_status' => isset($row['http_status']) && is_numeric($row['http_status']) ? (int) $row['http_status'] : null,
                'error' => is_string($row['error'] ?? null) ? (string) $row['error'] : null,
                'attempts' => (int) $row['attempts'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): WebhookEndpoint
    {
        $events = json_decode((string) $row['events'], true);
        if (!is_array($events)) {
            $events = [];
        }
        $normalized = [];
        foreach ($events as $event) {
            if (is_string($event)) {
                $normalized[] = $event;
            }
        }

        return new WebhookEndpoint(
            (string) $row['id'],
            new SiteId((string) $row['site_id']),
            (string) $row['url'],
            $this->decodeSecret((string) $row['secret']),
            $normalized,
            (int) $row['is_active'] === 1,
        );
    }

    private function encodeSecret(string $secret): string
    {
        return $this->secrets->encrypt($secret);
    }

    private function decodeSecret(string $raw): string
    {
        if (str_starts_with($raw, 'nx1:')) {
            try {
                return $this->secrets->decrypt($raw);
            } catch (\Throwable) {
                // Fall through to legacy plaintext if APP_KEY changed mid-flight.
            }
        }

        return $raw;
    }
}
