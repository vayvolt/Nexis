<?php

declare(strict_types=1);

namespace Nexis\Cache;

use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use PDO;

final class PageCache
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
        private string $storagePath,
        private int $ttlSeconds = 3600,
    ) {
    }

    public function key(string $locale, string $path, string $payloadHash): string
    {
        return hash('sha256', $locale . '|' . $path . '|' . $payloadHash);
    }

    public function get(SiteId $siteId, string $cacheKey): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT body_hash, expires_at FROM cache_pages
             WHERE site_id = :site_id AND cache_key = :cache_key LIMIT 1',
        );
        $stmt->execute(['site_id' => $siteId->value, 'cache_key' => $cacheKey]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $expires = $row['expires_at'] ?? null;
        if (is_string($expires) && $expires !== '') {
            $expiresAt = new \DateTimeImmutable($expires, new \DateTimeZone('UTC'));
            if ($expiresAt < $this->clock->now()) {
                $this->forget($siteId, $cacheKey);

                return null;
            }
        }

        $file = $this->filePath($siteId, (string) $row['body_hash']);
        if (!is_file($file)) {
            $this->forget($siteId, $cacheKey);

            return null;
        }
        $body = file_get_contents($file);

        return is_string($body) ? $body : null;
    }

    public function put(SiteId $siteId, string $cacheKey, string $html): void
    {
        $hash = hash('sha256', $html);
        $dir = dirname($this->filePath($siteId, $hash));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        file_put_contents($this->filePath($siteId, $hash), $html);

        $now = $this->clock->now();
        $expires = $now->modify('+' . $this->ttlSeconds . ' seconds');
        $check = $this->pdo->prepare(
            'SELECT id FROM cache_pages WHERE site_id = :site_id AND cache_key = :cache_key LIMIT 1',
        );
        $check->execute(['site_id' => $siteId->value, 'cache_key' => $cacheKey]);
        $existingId = $check->fetchColumn();
        if (is_string($existingId) && $existingId !== '') {
            $stmt = $this->pdo->prepare(
                'UPDATE cache_pages
                 SET body_hash = :body_hash, stored_at = :stored_at, expires_at = :expires_at
                 WHERE id = :id',
            );
            $stmt->execute([
                'body_hash' => $hash,
                'stored_at' => $now->format('Y-m-d H:i:s.v'),
                'expires_at' => $expires->format('Y-m-d H:i:s.v'),
                'id' => $existingId,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO cache_pages (id, site_id, cache_key, body_hash, stored_at, expires_at)
                 VALUES (:id, :site_id, :cache_key, :body_hash, :stored_at, :expires_at)',
            );
            $stmt->execute([
                'id' => Uuid::v7(),
                'site_id' => $siteId->value,
                'cache_key' => $cacheKey,
                'body_hash' => $hash,
                'stored_at' => $now->format('Y-m-d H:i:s.v'),
                'expires_at' => $expires->format('Y-m-d H:i:s.v'),
            ]);
        }
    }

    public function invalidateSite(SiteId $siteId): void
    {
        $stmt = $this->pdo->prepare('SELECT body_hash FROM cache_pages WHERE site_id = :site_id');
        $stmt->execute(['site_id' => $siteId->value]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $hash) {
            if (is_string($hash)) {
                $file = $this->filePath($siteId, $hash);
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $del = $this->pdo->prepare('DELETE FROM cache_pages WHERE site_id = :site_id');
        $del->execute(['site_id' => $siteId->value]);
    }

    public function forget(SiteId $siteId, string $cacheKey): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT body_hash FROM cache_pages WHERE site_id = :site_id AND cache_key = :cache_key LIMIT 1',
        );
        $stmt->execute(['site_id' => $siteId->value, 'cache_key' => $cacheKey]);
        $hash = $stmt->fetchColumn();
        if (is_string($hash)) {
            $file = $this->filePath($siteId, $hash);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $del = $this->pdo->prepare(
            'DELETE FROM cache_pages WHERE site_id = :site_id AND cache_key = :cache_key',
        );
        $del->execute(['site_id' => $siteId->value, 'cache_key' => $cacheKey]);
    }

    private function filePath(SiteId $siteId, string $bodyHash): string
    {
        return $this->storagePath
            . DIRECTORY_SEPARATOR . $siteId->value
            . DIRECTORY_SEPARATOR . $bodyHash . '.html';
    }
}
