<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;
use PDOException;

/**
 * Stores Idempotency-Key → response for 24h per site (docs/07 §7.8).
 */
final class IdempotencyStore
{
    public const TTL_SECONDS = 86400;

    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    /**
     * @return array{request_hash: string, status_code: int, response: array<string, mixed>}|null
     */
    public function find(SiteId $siteId, string $key): ?array
    {
        $key = trim($key);
        if ($key === '' || strlen($key) > 190) {
            return null;
        }
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'SELECT request_hash, response, status_code, expires_at
             FROM idempotency_keys
             WHERE site_id = :site_id AND `key` = :key
             LIMIT 1',
        );
        $stmt->execute(['site_id' => $siteId->value, 'key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if ((string) ($row['expires_at'] ?? '') < $now) {
            $this->delete($siteId, $key);

            return null;
        }
        $response = [];
        $raw = $row['response'] ?? null;
        if (is_string($raw) && $raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $response = is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                $response = [];
            }
        }

        return [
            'request_hash' => (string) $row['request_hash'],
            'status_code' => (int) $row['status_code'],
            'response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    public function remember(
        SiteId $siteId,
        string $key,
        string $requestHash,
        int $statusCode,
        array $response,
    ): void {
        $key = trim($key);
        if ($key === '' || strlen($key) > 190) {
            return;
        }
        $now = $this->clock->now();
        $expires = $now->modify('+' . self::TTL_SECONDS . ' seconds');
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO idempotency_keys
                    (id, site_id, `key`, request_hash, response, status_code, created_at, expires_at)
                 VALUES
                    (:id, :site_id, :key, :request_hash, :response, :status_code, :created_at, :expires_at)',
            );
            $stmt->execute([
                'id' => Uuid::v7(),
                'site_id' => $siteId->value,
                'key' => $key,
                'request_hash' => $requestHash,
                'response' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'status_code' => $statusCode,
                'created_at' => $now->format('Y-m-d H:i:s.v'),
                'expires_at' => $expires->format('Y-m-d H:i:s.v'),
            ]);
        } catch (PDOException) {
            // Concurrent writer won — caller should re-find.
        }
    }

    public function delete(SiteId $siteId, string $key): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM idempotency_keys WHERE site_id = :site_id AND `key` = :key',
        );
        $stmt->execute(['site_id' => $siteId->value, 'key' => $key]);
    }

    public function purgeExpired(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM idempotency_keys WHERE expires_at < :now');
        $stmt->execute(['now' => $this->clock->now()->format('Y-m-d H:i:s.v')]);

        return $stmt->rowCount();
    }

    public static function hashRequest(string ...$parts): string
    {
        return hash('sha256', implode("\0", $parts));
    }
}
