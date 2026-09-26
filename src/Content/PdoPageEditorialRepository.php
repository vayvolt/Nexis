<?php

declare(strict_types=1);

namespace Nexis\Content;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class PdoPageEditorialRepository implements PageEditorialRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function listForPage(PageId $pageId, int $limit = 50): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.display_name AS author_name
             FROM page_editorial_items e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.page_id = :page_id
             ORDER BY e.created_at DESC
             LIMIT ' . $limit,
        );
        $stmt->execute(['page_id' => $pageId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $out[] = $this->map($row);
            }
        }

        return $out;
    }

    public function findById(PageEditorialItemId $id, PageId $pageId): ?PageEditorialItem
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, u.display_name AS author_name
             FROM page_editorial_items e
             LEFT JOIN users u ON u.id = e.created_by
             WHERE e.id = :id AND e.page_id = :page_id
             LIMIT 1',
        );
        $stmt->execute([
            'id' => $id->value,
            'page_id' => $pageId->value,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function save(PageEditorialItem $item): void
    {
        $check = $this->pdo->prepare(
            'SELECT 1 FROM page_editorial_items WHERE id = :id LIMIT 1',
        );
        $check->execute(['id' => $item->id->value]);
        $exists = $check->fetchColumn() !== false;

        if ($exists) {
            $stmt = $this->pdo->prepare(
                'UPDATE page_editorial_items
                 SET body = :body,
                     status = :status,
                     updated_at = :updated_at,
                     resolved_at = :resolved_at,
                     resolved_by = :resolved_by
                 WHERE id = :id AND page_id = :page_id',
            );
            $stmt->execute([
                'id' => $item->id->value,
                'page_id' => $item->pageId->value,
                'body' => $item->body,
                'status' => $item->status,
                'updated_at' => $item->updatedAt->format('Y-m-d H:i:s.v'),
                'resolved_at' => $item->resolvedAt?->format('Y-m-d H:i:s.v'),
                'resolved_by' => $item->resolvedBy,
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO page_editorial_items
                (id, page_id, kind, body, status, created_by, created_at, updated_at, resolved_at, resolved_by)
             VALUES
                (:id, :page_id, :kind, :body, :status, :created_by, :created_at, :updated_at, :resolved_at, :resolved_by)',
        );
        $stmt->execute([
            'id' => $item->id->value,
            'page_id' => $item->pageId->value,
            'kind' => $item->kind,
            'body' => $item->body,
            'status' => $item->status,
            'created_by' => $item->createdBy,
            'created_at' => $item->createdAt->format('Y-m-d H:i:s.v'),
            'updated_at' => $item->updatedAt->format('Y-m-d H:i:s.v'),
            'resolved_at' => $item->resolvedAt?->format('Y-m-d H:i:s.v'),
            'resolved_by' => $item->resolvedBy,
        ]);
    }

    public function delete(PageEditorialItemId $id, PageId $pageId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM page_editorial_items WHERE id = :id AND page_id = :page_id',
        );
        $stmt->execute([
            'id' => $id->value,
            'page_id' => $pageId->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): PageEditorialItem
    {
        $createdBy = $row['created_by'] ?? null;
        $resolvedBy = $row['resolved_by'] ?? null;
        $authorName = $row['author_name'] ?? null;
        $resolvedAt = $row['resolved_at'] ?? null;

        return new PageEditorialItem(
            new PageEditorialItemId((string) $row['id']),
            new PageId((string) $row['page_id']),
            (string) $row['kind'],
            (string) $row['body'],
            (string) $row['status'],
            is_string($createdBy) && $createdBy !== '' ? $createdBy : null,
            new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
            new DateTimeImmutable((string) $row['updated_at'], new DateTimeZone('UTC')),
            is_string($resolvedAt) && $resolvedAt !== ''
                ? new DateTimeImmutable($resolvedAt, new DateTimeZone('UTC'))
                : null,
            is_string($resolvedBy) && $resolvedBy !== '' ? $resolvedBy : null,
            is_string($authorName) && $authorName !== '' ? $authorName : null,
        );
    }
}
