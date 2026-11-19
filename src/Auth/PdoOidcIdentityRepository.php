<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\Support\Uuid;
use PDO;

final class PdoOidcIdentityRepository implements OidcIdentityRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function findByProviderSubject(string $provider, string $subject): ?OidcIdentity
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, provider, subject, email_at_link
             FROM oauth_identities
             WHERE provider = :provider AND subject = :subject
             LIMIT 1',
        );
        $stmt->execute([
            'provider' => $provider,
            'subject' => $subject,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->map($row);
    }

    public function link(UserId $userId, string $provider, string $subject, ?string $emailAtLink): OidcIdentity
    {
        $existing = $this->findByProviderSubject($provider, $subject);
        if ($existing !== null) {
            return $existing;
        }

        $id = Uuid::v7();
        $now = gmdate('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_identities
                (id, user_id, provider, subject, email_at_link, created_at, updated_at)
             VALUES
                (:id, :user_id, :provider, :subject, :email_at_link, :created_at, :updated_at)',
        );
        $stmt->execute([
            'id' => $id,
            'user_id' => $userId->value,
            'provider' => $provider,
            'subject' => $subject,
            'email_at_link' => $emailAtLink,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new OidcIdentity($id, $userId, $provider, $subject, $emailAtLink);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): OidcIdentity
    {
        $email = $row['email_at_link'] ?? null;

        return new OidcIdentity(
            (string) $row['id'],
            new UserId((string) $row['user_id']),
            (string) $row['provider'],
            (string) $row['subject'],
            is_string($email) && $email !== '' ? $email : null,
        );
    }
}
