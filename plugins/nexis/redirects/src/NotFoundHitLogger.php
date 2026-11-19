<?php

declare(strict_types=1);

namespace Nexis\Plugins\Redirects;

use Nexis\Event\PublicNotFound;
use Nexis\Support\Uuid;
use PDO;

final class NotFoundHitLogger
{
    public function __construct(private PDO $pdo)
    {
    }

    public function onPublicNotFound(PublicNotFound $event): void
    {
        $path = $this->normalizePath($event->path);
        if ($path === '' || str_starts_with($path, '/admin') || str_starts_with($path, '/assets')) {
            return;
        }

        $locale = $event->locale !== '' ? $event->locale : '';
        $now = gmdate('Y-m-d H:i:s.v');
        $referer = $this->clip($event->referer, 1000);
        $ua = $this->clip($event->userAgent, 500);

        $stmt = $this->pdo->prepare(
            'INSERT INTO plugin_nexis_not_found_hits
                (id, site_id, locale, path, hit_count, last_referer, last_user_agent, first_seen_at, last_seen_at)
             VALUES
                (:id, :site_id, :locale, :path, 1, :referer, :ua, :first_seen, :last_seen)
             ON DUPLICATE KEY UPDATE
                hit_count = hit_count + 1,
                last_referer = VALUES(last_referer),
                last_user_agent = VALUES(last_user_agent),
                last_seen_at = VALUES(last_seen_at)',
        );
        try {
            $stmt->execute([
                'id' => Uuid::v7(),
                'site_id' => $event->siteId->value,
                'locale' => $locale,
                'path' => $path,
                'referer' => $referer,
                'ua' => $ua,
                'first_seen' => $now,
                'last_seen' => $now,
            ]);
        } catch (\Throwable) {
            // Logging must never break the 404 response.
        }
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }
        if (strlen($path) > 500) {
            $path = substr($path, 0, 500);
        }

        return $path;
    }

    private function clip(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (strlen($value) > $max) {
            return substr($value, 0, $max);
        }

        return $value;
    }
}
