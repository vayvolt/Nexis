<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Site\SiteId;
use PDO;

final class PdoPatternRepository implements PatternRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function findById(PatternId $id, SiteId $siteId): ?BlockPattern
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM block_patterns WHERE id = :id AND site_id = :site_id LIMIT 1',
        );
        $stmt->execute([
            'id' => $id->value,
            'site_id' => $siteId->value,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->map($row) : null;
    }

    public function listBySite(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM block_patterns WHERE site_id = :site_id ORDER BY name ASC, created_at ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $out[] = $this->map($row);
            }
        }

        return $out;
    }

    public function save(BlockPattern $pattern): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO block_patterns
                (id, site_id, name, description, document, created_by, created_at, updated_at)
             VALUES
                (:id, :site_id, :name, :description, :document, :created_by, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                document = VALUES(document),
                updated_at = VALUES(updated_at)',
        );
        $stmt->execute([
            'id' => $pattern->id->value,
            'site_id' => $pattern->siteId->value,
            'name' => $pattern->name,
            'description' => $pattern->description,
            'document' => json_encode($pattern->document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'created_by' => $pattern->createdBy,
            'created_at' => $pattern->createdAt->format('Y-m-d H:i:s.v'),
            'updated_at' => $pattern->updatedAt->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function delete(PatternId $id, SiteId $siteId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM block_patterns WHERE id = :id AND site_id = :site_id',
        );
        $stmt->execute([
            'id' => $id->value,
            'site_id' => $siteId->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): BlockPattern
    {
        $raw = $row['document'] ?? '{}';
        $document = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($document)) {
            $document = [];
        }
        $desc = $row['description'] ?? null;
        $createdBy = $row['created_by'] ?? null;

        return new BlockPattern(
            new PatternId((string) $row['id']),
            new SiteId((string) $row['site_id']),
            (string) $row['name'],
            is_string($desc) && $desc !== '' ? $desc : null,
            $document,
            is_string($createdBy) && $createdBy !== '' ? $createdBy : null,
            new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
            new \DateTimeImmutable((string) $row['updated_at'], new \DateTimeZone('UTC')),
        );
    }
}
