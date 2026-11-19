<?php

declare(strict_types=1);

namespace Nexis\Site;

use Nexis\Security\SecretBox;
use Nexis\Support\Uuid;
use PDO;

final class PdoSiteSettingsRepository
{
    public function __construct(
        private PDO $pdo,
        private SecretBox $secrets,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT `key` FROM site_settings WHERE site_id = :site_id ORDER BY `key` ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            $out[$key] = $this->get($siteId, $key);
        }

        return $out;
    }

    public function get(SiteId $siteId, string $key, mixed $default = null): mixed
    {
        $stmt = $this->pdo->prepare(
            'SELECT value, encrypted FROM site_settings WHERE site_id = :site_id AND `key` = :key LIMIT 1',
        );
        $stmt->execute(['site_id' => $siteId->value, 'key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return $default;
        }
        $raw = $row['value'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return $default;
        }
        $encrypted = (int) ($row['encrypted'] ?? 0) === 1;
        if ($encrypted) {
            try {
                $cipher = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (!is_string($cipher) || $cipher === '') {
                    return $default;
                }
                $raw = $this->secrets->decrypt($cipher);
            } catch (\Throwable) {
                return $default;
            }
        }
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function set(SiteId $siteId, string $key, mixed $value, ?bool $encrypt = null): void
    {
        $shouldEncrypt = $encrypt ?? SecretBox::isSecretKey($key);
        $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if ($shouldEncrypt) {
            // JSON column requires valid JSON; store ciphertext as a JSON string.
            $encoded = json_encode($this->secrets->encrypt($encoded), JSON_THROW_ON_ERROR);
        }
        $now = gmdate('Y-m-d H:i:s.v');
        $find = $this->pdo->prepare(
            'SELECT id FROM site_settings WHERE site_id = :site_id AND `key` = :key LIMIT 1',
        );
        $find->execute(['site_id' => $siteId->value, 'key' => $key]);
        $id = $find->fetchColumn();
        if (is_string($id) && $id !== '') {
            $upd = $this->pdo->prepare(
                'UPDATE site_settings
                 SET value = :value, encrypted = :encrypted, updated_at = :updated_at
                 WHERE id = :id',
            );
            $upd->execute([
                'value' => $encoded,
                'encrypted' => $shouldEncrypt ? 1 : 0,
                'updated_at' => $now,
                'id' => $id,
            ]);

            return;
        }
        $ins = $this->pdo->prepare(
            'INSERT INTO site_settings (id, site_id, `key`, value, encrypted, updated_at)
             VALUES (:id, :site_id, :key, :value, :encrypted, :updated_at)',
        );
        $ins->execute([
            'id' => Uuid::v7(),
            'site_id' => $siteId->value,
            'key' => $key,
            'value' => $encoded,
            'encrypted' => $shouldEncrypt ? 1 : 0,
            'updated_at' => $now,
        ]);
    }

    public function delete(SiteId $siteId, string $key): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM site_settings WHERE site_id = :site_id AND `key` = :key',
        );
        $stmt->execute(['site_id' => $siteId->value, 'key' => $key]);
    }

    public function deleteByKeyPrefix(SiteId $siteId, string $prefix): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM site_settings WHERE site_id = :site_id AND `key` LIKE :prefix',
        );
        $stmt->execute([
            'site_id' => $siteId->value,
            'prefix' => $prefix . '%',
        ]);

        return $stmt->rowCount();
    }
}
