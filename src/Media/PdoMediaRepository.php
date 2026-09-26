<?php

declare(strict_types=1);

namespace Nexis\Media;

use Nexis\Site\SiteId;
use Nexis\Support\Clock;
use PDO;

final class PdoMediaRepository implements MediaRepository
{
    public function __construct(
        private PDO $pdo,
        private Clock $clock,
    ) {
    }

    public function findById(MediaId $id, SiteId $siteId): ?MediaAsset
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media_assets WHERE id = :id AND site_id = :site_id AND deleted_at IS NULL LIMIT 1',
        );
        $stmt->execute([
            'id' => $id->value,
            'site_id' => $siteId->value,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function listBySite(SiteId $siteId, ?MediaFolderId $folderId = null, bool $unfiledOnly = false): array
    {
        if ($unfiledOnly) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM media_assets
                 WHERE site_id = :site_id AND deleted_at IS NULL AND folder_id IS NULL
                 ORDER BY created_at DESC',
            );
            $stmt->execute(['site_id' => $siteId->value]);
        } elseif ($folderId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM media_assets
                 WHERE site_id = :site_id AND deleted_at IS NULL AND folder_id = :folder_id
                 ORDER BY created_at DESC',
            );
            $stmt->execute([
                'site_id' => $siteId->value,
                'folder_id' => $folderId->value,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM media_assets WHERE site_id = :site_id AND deleted_at IS NULL ORDER BY created_at DESC',
            );
            $stmt->execute(['site_id' => $siteId->value]);
        }
        $assets = [];
        foreach ($stmt->fetchAll() as $row) {
            if (is_array($row)) {
                $assets[] = $this->map($row);
            }
        }

        return $assets;
    }

    public function listFolders(SiteId $siteId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media_folders WHERE site_id = :site_id ORDER BY sort_order ASC, name ASC',
        );
        $stmt->execute(['site_id' => $siteId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $out[] = $this->mapFolder($row);
            }
        }

        return $out;
    }

    public function findFolder(MediaFolderId $id, SiteId $siteId): ?MediaFolder
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media_folders WHERE id = :id AND site_id = :site_id LIMIT 1',
        );
        $stmt->execute([
            'id' => $id->value,
            'site_id' => $siteId->value,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapFolder($row) : null;
    }

    public function saveFolder(MediaFolder $folder): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media_folders (id, site_id, name, sort_order, created_at)
             VALUES (:id, :site_id, :name, :sort_order, :created_at)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                sort_order = VALUES(sort_order)',
        );
        $stmt->execute([
            'id' => $folder->id->value,
            'site_id' => $folder->siteId->value,
            'name' => $folder->name,
            'sort_order' => $folder->sortOrder,
            'created_at' => $folder->createdAt->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function deleteFolder(MediaFolderId $id, SiteId $siteId): bool
    {
        $clear = $this->pdo->prepare(
            'UPDATE media_assets SET folder_id = NULL
             WHERE site_id = :site_id AND folder_id = :folder_id AND deleted_at IS NULL',
        );
        $clear->execute([
            'site_id' => $siteId->value,
            'folder_id' => $id->value,
        ]);
        $stmt = $this->pdo->prepare(
            'DELETE FROM media_folders WHERE id = :id AND site_id = :site_id',
        );
        $stmt->execute([
            'id' => $id->value,
            'site_id' => $siteId->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function moveToFolder(MediaId $id, SiteId $siteId, ?MediaFolderId $folderId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media_assets SET folder_id = :folder_id
             WHERE id = :id AND site_id = :site_id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'folder_id' => $folderId?->value,
            'id' => $id->value,
            'site_id' => $siteId->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function save(MediaAsset $asset): void
    {
        $now = ($asset->createdAt ?? $this->clock->now())->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare(
            'INSERT INTO media_assets (
                id, site_id, folder_id, disk_key, original_name, alt_text, mime, extension, byte_size, width, height, focus_x, focus_y, checksum, created_at
            ) VALUES (
                :id, :site_id, :folder_id, :disk_key, :original_name, :alt_text, :mime, :extension, :byte_size, :width, :height, :focus_x, :focus_y, :checksum, :created_at
            )',
        );
        $stmt->execute([
            'id' => $asset->id->value,
            'site_id' => $asset->siteId->value,
            'folder_id' => $asset->folderId?->value,
            'disk_key' => $asset->diskKey,
            'original_name' => $asset->originalName,
            'alt_text' => $asset->altText,
            'mime' => $asset->mime,
            'extension' => $asset->extension,
            'byte_size' => $asset->byteSize,
            'width' => $asset->width,
            'height' => $asset->height,
            'focus_x' => $asset->focusX,
            'focus_y' => $asset->focusY,
            'checksum' => $asset->checksum,
            'created_at' => $now,
        ]);
    }

    public function updateAlt(MediaAsset $asset): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media_assets SET alt_text = :alt_text WHERE id = :id AND site_id = :site_id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'alt_text' => $asset->altText,
            'id' => $asset->id->value,
            'site_id' => $asset->siteId->value,
        ]);
    }

    public function updateFocus(MediaAsset $asset): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media_assets
             SET focus_x = :focus_x, focus_y = :focus_y
             WHERE id = :id AND site_id = :site_id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'focus_x' => $asset->focusX,
            'focus_y' => $asset->focusY,
            'id' => $asset->id->value,
            'site_id' => $asset->siteId->value,
        ]);
    }

    public function softDelete(MediaId $id, SiteId $siteId): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE media_assets
             SET deleted_at = :deleted_at
             WHERE id = :id AND site_id = :site_id AND deleted_at IS NULL',
        );
        $stmt->execute([
            'deleted_at' => $this->clock->now()->format('Y-m-d H:i:s.v'),
            'id' => $id->value,
            'site_id' => $siteId->value,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countBySite(SiteId $siteId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM media_assets WHERE site_id = :site_id AND deleted_at IS NULL',
        );
        $stmt->execute(['site_id' => $siteId->value]);

        return (int) $stmt->fetchColumn();
    }

    public function saveVariant(MediaVariant $variant): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media_variants (id, asset_id, handle, disk_key, mime, width, height, byte_size)
             VALUES (:id, :asset_id, :handle, :disk_key, :mime, :width, :height, :byte_size)
             ON DUPLICATE KEY UPDATE
                disk_key = VALUES(disk_key),
                mime = VALUES(mime),
                width = VALUES(width),
                height = VALUES(height),
                byte_size = VALUES(byte_size)',
        );
        $stmt->execute([
            'id' => $variant->id,
            'asset_id' => $variant->assetId->value,
            'handle' => $variant->handle,
            'disk_key' => $variant->diskKey,
            'mime' => $variant->mime,
            'width' => $variant->width,
            'height' => $variant->height,
            'byte_size' => $variant->byteSize,
        ]);
    }

    public function findVariant(MediaId $assetId, string $handle): ?MediaVariant
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media_variants WHERE asset_id = :asset_id AND handle = :handle LIMIT 1',
        );
        $stmt->execute([
            'asset_id' => $assetId->value,
            'handle' => $handle,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapVariant($row) : null;
    }

    public function variantsFor(MediaId $assetId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM media_variants WHERE asset_id = :asset_id ORDER BY handle ASC',
        );
        $stmt->execute(['asset_id' => $assetId->value]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (is_array($row)) {
                $out[] = $this->mapVariant($row);
            }
        }

        return $out;
    }

    public function hasVariant(MediaId $assetId, string $handle): bool
    {
        return $this->findVariant($assetId, $handle) !== null;
    }

    public function deleteVariants(MediaId $assetId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM media_variants WHERE asset_id = :asset_id');
        $stmt->execute(['asset_id' => $assetId->value]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function map(array $row): MediaAsset
    {
        $width = $row['width'] ?? null;
        $height = $row['height'] ?? null;
        $createdRaw = $row['created_at'] ?? null;
        $createdAt = null;
        if (is_string($createdRaw) && $createdRaw !== '') {
            try {
                $createdAt = new \DateTimeImmutable($createdRaw, new \DateTimeZone('UTC'));
            } catch (\Exception) {
                $createdAt = null;
            }
        }
        $alt = $row['alt_text'] ?? null;
        $folderRaw = $row['folder_id'] ?? null;
        $folderId = is_string($folderRaw) && $folderRaw !== ''
            ? new MediaFolderId($folderRaw)
            : null;
        $focusX = isset($row['focus_x']) && is_numeric($row['focus_x']) ? (float) $row['focus_x'] : 50.0;
        $focusY = isset($row['focus_y']) && is_numeric($row['focus_y']) ? (float) $row['focus_y'] : 50.0;

        return new MediaAsset(
            new MediaId((string) $row['id']),
            new SiteId((string) $row['site_id']),
            (string) $row['disk_key'],
            (string) $row['original_name'],
            is_string($alt) && $alt !== '' ? $alt : null,
            (string) $row['mime'],
            (string) $row['extension'],
            (int) $row['byte_size'],
            is_numeric($width) ? (int) $width : null,
            is_numeric($height) ? (int) $height : null,
            (string) $row['checksum'],
            $createdAt,
            $folderId,
            $focusX,
            $focusY,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapFolder(array $row): MediaFolder
    {
        return new MediaFolder(
            new MediaFolderId((string) $row['id']),
            new SiteId((string) $row['site_id']),
            (string) $row['name'],
            (int) ($row['sort_order'] ?? 0),
            new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapVariant(array $row): MediaVariant
    {
        $width = $row['width'] ?? null;
        $height = $row['height'] ?? null;

        return new MediaVariant(
            (string) $row['id'],
            new MediaId((string) $row['asset_id']),
            (string) $row['handle'],
            (string) $row['disk_key'],
            (string) $row['mime'],
            is_numeric($width) ? (int) $width : null,
            is_numeric($height) ? (int) $height : null,
            (int) $row['byte_size'],
        );
    }
}
