<?php

declare(strict_types=1);

namespace Nexis\Search;

use Nexis\Site\SiteId;
use PDO;

final class MariaDbFulltextSearch implements SearchPort
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function search(SiteId $siteId, string $locale, string $query, int $limit = 20): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT id, title, path, locale, search_text,
                    MATCH(title, meta_title, search_text) AGAINST (:q IN NATURAL LANGUAGE MODE) AS score
             FROM pages
             WHERE site_id = :site_id
               AND locale = :locale
               AND status = :status
               AND deleted_at IS NULL
               AND MATCH(title, meta_title, search_text) AGAINST (:q2 IN NATURAL LANGUAGE MODE)
             ORDER BY score DESC
             LIMIT ' . $limit,
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'locale' => $locale,
            'status' => 'published',
            'q' => $query,
            'q2' => $query,
        ]);
        $hits = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = (string) ($row['search_text'] ?? '');
            $excerpt = mb_substr($text, 0, 160);
            $hits[] = new SearchHit(
                (string) $row['id'],
                (string) $row['title'],
                (string) $row['path'],
                (string) $row['locale'],
                $excerpt,
            );
        }

        return $hits;
    }
}
