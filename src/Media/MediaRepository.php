<?php

declare(strict_types=1);

namespace Nexis\Media;

use Nexis\Site\SiteId;

interface MediaRepository
{
    public function findById(MediaId $id, SiteId $siteId): ?MediaAsset;

    /**
     * @return list<MediaAsset>
     */
    public function listBySite(SiteId $siteId, ?MediaFolderId $folderId = null, bool $unfiledOnly = false): array;

    /**
     * @return list<MediaFolder>
     */
    public function listFolders(SiteId $siteId): array;

    public function findFolder(MediaFolderId $id, SiteId $siteId): ?MediaFolder;

    public function saveFolder(MediaFolder $folder): void;

    public function deleteFolder(MediaFolderId $id, SiteId $siteId): bool;

    public function moveToFolder(MediaId $id, SiteId $siteId, ?MediaFolderId $folderId): bool;

    public function save(MediaAsset $asset): void;

    public function updateAlt(MediaAsset $asset): void;

    public function updateFocus(MediaAsset $asset): void;

    public function softDelete(MediaId $id, SiteId $siteId): bool;

    public function countBySite(SiteId $siteId): int;

    public function saveVariant(MediaVariant $variant): void;

    public function findVariant(MediaId $assetId, string $handle): ?MediaVariant;

    /**
     * @return list<MediaVariant>
     */
    public function variantsFor(MediaId $assetId): array;

    public function hasVariant(MediaId $assetId, string $handle): bool;

    public function deleteVariants(MediaId $assetId): void;
}
