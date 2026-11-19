<?php

declare(strict_types=1);

namespace Nexis\Mail;

use PDO;

final class PdoMailLogRepository implements MailLogRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function record(MailLogEntry $entry): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO mail_log (
                id, site_id, transport, status, to_addresses, from_address, subject,
                body_text, reply_to, context, error_message, smtp_log, created_at
             ) VALUES (
                :id, :site_id, :transport, :status, :to_addresses, :from_address, :subject,
                :body_text, :reply_to, :context, :error_message, :smtp_log, :created_at
             )',
        );
        $stmt->execute([
            'id' => $entry->id,
            'site_id' => $entry->siteId,
            'transport' => $entry->transport,
            'status' => $entry->status,
            'to_addresses' => json_encode($entry->to, JSON_THROW_ON_ERROR),
            'from_address' => $entry->fromAddress,
            'subject' => $entry->subject,
            'body_text' => $entry->bodyText,
            'reply_to' => $entry->replyTo,
            'context' => $entry->context,
            'error_message' => $entry->errorMessage,
            'smtp_log' => $entry->smtpLog,
            'created_at' => $entry->createdAt,
        ]);
    }

    public function recent(int $limit = 100, ?string $status = null): array
    {
        $limit = max(1, min(500, $limit));
        if ($status !== null && $status !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM mail_log WHERE status = :status ORDER BY created_at DESC LIMIT ' . $limit,
            );
            $stmt->execute(['status' => $status]);
        } else {
            $stmt = $this->pdo->query(
                'SELECT * FROM mail_log ORDER BY created_at DESC LIMIT ' . $limit,
            );
        }
        if ($stmt === false) {
            return [];
        }

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $this->hydrate($row);
        }

        return $rows;
    }

    public function find(string $id): ?MailLogEntry
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mail_log WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): MailLogEntry
    {
        $toRaw = $row['to_addresses'] ?? '[]';
        $to = [];
        if (is_string($toRaw) && $toRaw !== '') {
            try {
                $decoded = json_decode($toRaw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach ($decoded as $addr) {
                        if (is_string($addr) && $addr !== '') {
                            $to[] = $addr;
                        }
                    }
                }
            } catch (\JsonException) {
                $to = [trim($toRaw)];
            }
        }

        return new MailLogEntry(
            (string) $row['id'],
            isset($row['site_id']) && is_string($row['site_id']) ? $row['site_id'] : null,
            (string) $row['transport'],
            (string) $row['status'],
            $to,
            isset($row['from_address']) && is_string($row['from_address']) ? $row['from_address'] : null,
            (string) $row['subject'],
            (string) ($row['body_text'] ?? ''),
            isset($row['reply_to']) && is_string($row['reply_to']) ? $row['reply_to'] : null,
            isset($row['context']) && is_string($row['context']) ? $row['context'] : null,
            isset($row['error_message']) && is_string($row['error_message']) ? $row['error_message'] : null,
            isset($row['smtp_log']) && is_string($row['smtp_log']) ? $row['smtp_log'] : null,
            (string) $row['created_at'],
        );
    }
}
