<?php

declare(strict_types=1);

namespace Nexis\Queue;

use Nexis\Support\Clock;
use PDO;

final class JobQueue
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $queue, array $payload, int $delaySeconds = 0): void
    {
        $available = $this->clock->now()->modify('+' . max(0, $delaySeconds) . ' seconds');
        $stmt = $this->pdo->prepare(
            'INSERT INTO jobs (queue, payload, attempts, available_at, created_at)
             VALUES (:queue, :payload, 0, :available_at, :created_at)',
        );
        $stmt->execute([
            'queue' => $queue,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'available_at' => $available->format('Y-m-d H:i:s.v'),
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
        ]);
    }

    /**
     * @return list<array{id: int, payload: array<string, mixed>, attempts: int}>
     */
    public function reserve(string $queue, int $limit = 10): array
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'SELECT id, payload, attempts FROM jobs
             WHERE queue = :queue AND available_at <= :now AND reserved_at IS NULL
             ORDER BY id ASC LIMIT ' . max(1, $limit),
        );
        $stmt->execute(['queue' => $queue, 'now' => $now]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) $row['id'];
            $lock = $this->pdo->prepare(
                'UPDATE jobs SET reserved_at = :reserved_at, attempts = attempts + 1
                 WHERE id = :id AND reserved_at IS NULL',
            );
            $lock->execute(['reserved_at' => $now, 'id' => $id]);
            if ($lock->rowCount() !== 1) {
                continue;
            }
            $decoded = json_decode((string) $row['payload'], true);
            if (!is_array($decoded)) {
                $this->delete($id);
                continue;
            }
            /** @var array<string, mixed> $decoded */
            $rows[] = [
                'id' => $id,
                'payload' => $decoded,
                'attempts' => (int) $row['attempts'] + 1,
            ];
        }

        return $rows;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM jobs WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function release(int $id, int $delaySeconds): void
    {
        $available = $this->clock->now()->modify('+' . max(0, $delaySeconds) . ' seconds');
        $stmt = $this->pdo->prepare(
            'UPDATE jobs SET reserved_at = NULL, available_at = :available_at WHERE id = :id',
        );
        $stmt->execute([
            'available_at' => $available->format('Y-m-d H:i:s.v'),
            'id' => $id,
        ]);
    }

    /**
     * Move a job to failed_jobs and remove it from the queue.
     *
     * @param array<string, mixed> $payload
     */
    public function fail(int $id, string $queue, array $payload, string $exception): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO failed_jobs (queue, payload, exception, failed_at)
             VALUES (:queue, :payload, :exception, :failed_at)',
        );
        $stmt->execute([
            'queue' => $queue,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'exception' => mb_substr($exception, 0, 65000),
            'failed_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
        ]);
        $this->delete($id);
    }

    public function pendingCount(?string $queue = null): int
    {
        if ($queue === null || $queue === '') {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM jobs');
            return (int) ($stmt !== false ? $stmt->fetchColumn() : 0);
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM jobs WHERE queue = :queue');
        $stmt->execute(['queue' => $queue]);

        return (int) $stmt->fetchColumn();
    }

    public function failedCount(?string $queue = null): int
    {
        if ($queue === null || $queue === '') {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM failed_jobs');

            return (int) ($stmt !== false ? $stmt->fetchColumn() : 0);
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM failed_jobs WHERE queue = :queue');
        $stmt->execute(['queue' => $queue]);

        return (int) $stmt->fetchColumn();
    }

    public function reservedCount(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM jobs WHERE reserved_at IS NOT NULL');

        return (int) ($stmt !== false ? $stmt->fetchColumn() : 0);
    }

    /**
     * @return list<array{queue: string, pending: int}>
     */
    public function pendingByQueue(): array
    {
        $stmt = $this->pdo->query(
            'SELECT queue, COUNT(*) AS cnt FROM jobs GROUP BY queue ORDER BY cnt DESC, queue ASC',
        );
        if ($stmt === false) {
            return [];
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'queue' => (string) ($row['queue'] ?? ''),
                'pending' => (int) ($row['cnt'] ?? 0),
            ];
        }

        return $out;
    }
}
