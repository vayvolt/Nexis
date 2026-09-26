<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Auth\UserId;
use Nexis\Content\PageId;
use DateTimeImmutable;
use PDO;

final class PdoRevisionRepository implements RevisionRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function findById(RevisionId $id): ?PageRevision
    {
        $stmt = $this->pdo->prepare('SELECT * FROM page_revisions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function latestForPage(PageId $pageId): ?PageRevision
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM page_revisions WHERE page_id = :page_id ORDER BY created_at DESC LIMIT 1',
        );
        $stmt->execute(['page_id' => $pageId->value]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function listForPage(PageId $pageId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM page_revisions WHERE page_id = :page_id ORDER BY created_at DESC LIMIT ' . max(1, $limit),
        );
        $stmt->execute(['page_id' => $pageId->value]);
        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $items[] = $this->map($row);
            }
        }

        return $items;
    }

    public function save(PageRevision $revision): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO page_revisions (id, page_id, schema_version, document, document_hash, message, created_by, created_at)
             VALUES (:id, :page_id, :schema_version, :document, :document_hash, :message, :created_by, :created_at)',
        );
        $stmt->execute([
            'id' => $revision->id->value,
            'page_id' => $revision->pageId->value,
            'schema_version' => $revision->schemaVersion,
            'document' => json_encode($revision->document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'document_hash' => $revision->documentHash,
            'message' => $revision->message,
            'created_by' => $revision->createdBy?->value,
            'created_at' => $revision->createdAt->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): PageRevision
    {
        $document = $row['document'];
        if (is_string($document)) {
            $decoded = json_decode($document, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $decoded = $document;
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $createdBy = $row['created_by'] ?? null;

        return new PageRevision(
            new RevisionId((string) $row['id']),
            new PageId((string) $row['page_id']),
            (int) $row['schema_version'],
            $decoded,
            (string) $row['document_hash'],
            isset($row['message']) && is_string($row['message']) ? $row['message'] : null,
            is_string($createdBy) && $createdBy !== '' ? new UserId($createdBy) : null,
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
