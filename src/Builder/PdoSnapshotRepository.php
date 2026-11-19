<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Auth\UserId;
use Nexis\Content\PageId;
use DateTimeImmutable;
use PDO;

final class PdoSnapshotRepository implements SnapshotRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function findById(SnapshotId $id): ?PageSnapshot
    {
        $stmt = $this->pdo->prepare('SELECT * FROM page_snapshots WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id->value]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function save(PageSnapshot $snapshot): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO page_snapshots (id, page_id, revision_id, payload, payload_hash, published_by, published_at)
             VALUES (:id, :page_id, :revision_id, :payload, :payload_hash, :published_by, :published_at)',
        );
        $stmt->execute([
            'id' => $snapshot->id->value,
            'page_id' => $snapshot->pageId->value,
            'revision_id' => $snapshot->revisionId->value,
            'payload' => json_encode($snapshot->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'payload_hash' => $snapshot->payloadHash,
            'published_by' => $snapshot->publishedBy?->value,
            'published_at' => $snapshot->publishedAt->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): PageSnapshot
    {
        $payload = $row['payload'];
        if (is_string($payload)) {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $decoded = $payload;
        }
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $publishedBy = $row['published_by'] ?? null;

        return new PageSnapshot(
            new SnapshotId((string) $row['id']),
            new PageId((string) $row['page_id']),
            new RevisionId((string) $row['revision_id']),
            $decoded,
            (string) $row['payload_hash'],
            is_string($publishedBy) && $publishedBy !== '' ? new UserId($publishedBy) : null,
            new DateTimeImmutable((string) $row['published_at']),
        );
    }
}
