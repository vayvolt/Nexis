<?php

declare(strict_types=1);

namespace Nexis\Media;

use Nexis\Event\EventDispatcher;
use Nexis\Event\MediaUploaded;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

final class MediaLibrary
{
    private const ALLOWED = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];

    private const THUMB_MAX = 480;

    public function __construct(
        private MediaRepository $repository,
        private string $storagePath,
        private int $maxBytes = 10_485_760,
        private int $maxPixels = 25_000_000,
        private ?EventDispatcher $events = null,
    ) {
    }

    public function store(SiteId $siteId, UploadedFileInterface $file, string $altText = '', ?MediaFolderId $folderId = null): MediaAsset
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('media.upload_failed');
        }

        $original = $file->getClientFilename() ?? 'upload';
        $binary = (string) $file->getStream();
        if ($binary === '') {
            throw new InvalidArgumentException('media.empty');
        }
        if (strlen($binary) > $this->maxBytes) {
            throw new InvalidArgumentException('media.too_large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            throw new InvalidArgumentException('media.type');
        }

        if (str_starts_with($mime, 'image/')) {
            $size = @getimagesizefromstring($binary);
            if (!is_array($size)) {
                throw new InvalidArgumentException('media.type');
            }
            $pixels = (int) $size[0] * (int) $size[1];
            if ($pixels > $this->maxPixels) {
                throw new InvalidArgumentException('media.too_many_pixels');
            }
        }

        $altText = trim($altText);
        if (str_starts_with($mime, 'image/') && $altText === '') {
            throw new InvalidArgumentException('media.alt_required');
        }

        $extension = self::ALLOWED[$mime];
        $id = new MediaId(Uuid::v7());
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $relative = sprintf(
            '%s/%s/%s/%s.%s',
            $siteId->value,
            $now->format('Y'),
            $now->format('m'),
            $id->value,
            $extension,
        );
        $absoluteDir = $this->storagePath . DIRECTORY_SEPARATOR . dirname($relative);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('media.mkdir');
        }

        $absolute = $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $stored = $this->persistSafely($mime, $binary, $absolute);
        $width = null;
        $height = null;
        $size = getimagesize($absolute);
        if (is_array($size)) {
            $width = $size[0];
            $height = $size[1];
        }

        $asset = new MediaAsset(
            $id,
            $siteId,
            $relative,
            $original,
            $altText !== '' ? $altText : null,
            $mime,
            $extension,
            (int) filesize($absolute),
            $width,
            $height,
            hash('sha256', $stored),
            $now,
            $folderId,
        );
        $this->repository->save($asset);
        $this->generateVariants($asset, $absolute);
        $this->events?->dispatch(new MediaUploaded(
            $siteId,
            $asset->id,
            $asset->mime,
            $asset->diskKey,
            $asset->originalName,
        ));

        return $asset;
    }

    /**
     * Import binary with a predetermined id (site export/import), applying the same
     * MIME whitelist, size/pixel limits, and re-encode rules as store().
     */
    public function importBinary(
        SiteId $siteId,
        MediaId $id,
        string $binary,
        string $originalName,
        string $altText = '',
    ): MediaAsset {
        if ($binary === '') {
            throw new InvalidArgumentException('media.empty');
        }
        if (strlen($binary) > $this->maxBytes) {
            throw new InvalidArgumentException('media.too_large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($binary);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            throw new InvalidArgumentException('media.type');
        }

        if (str_starts_with($mime, 'image/')) {
            $size = @getimagesizefromstring($binary);
            if (!is_array($size)) {
                throw new InvalidArgumentException('media.type');
            }
            $pixels = (int) $size[0] * (int) $size[1];
            if ($pixels > $this->maxPixels) {
                throw new InvalidArgumentException('media.too_many_pixels');
            }
        }

        $altText = trim($altText);
        if (str_starts_with($mime, 'image/') && $altText === '') {
            throw new InvalidArgumentException('media.alt_required');
        }

        $extension = self::ALLOWED[$mime];
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $relative = sprintf(
            '%s/%s/%s/%s.%s',
            $siteId->value,
            $now->format('Y'),
            $now->format('m'),
            $id->value,
            $extension,
        );
        $absoluteDir = $this->storagePath . DIRECTORY_SEPARATOR . dirname($relative);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('media.mkdir');
        }

        $absolute = $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $stored = $this->persistSafely($mime, $binary, $absolute);
        $width = null;
        $height = null;
        $size = getimagesize($absolute);
        if (is_array($size)) {
            $width = $size[0];
            $height = $size[1];
        }

        $asset = new MediaAsset(
            $id,
            $siteId,
            $relative,
            $originalName !== '' ? $originalName : 'import.' . $extension,
            $altText !== '' ? $altText : null,
            $mime,
            $extension,
            (int) filesize($absolute),
            $width,
            $height,
            hash('sha256', $stored),
            $now,
        );
        $this->repository->save($asset);
        $this->generateVariants($asset, $absolute);

        return $asset;
    }

    public function updateAlt(MediaAsset $asset, string $altText): MediaAsset
    {
        $altText = trim($altText);
        if ($asset->isImage() && $altText === '') {
            throw new InvalidArgumentException('media.alt_required');
        }
        $updated = new MediaAsset(
            $asset->id,
            $asset->siteId,
            $asset->diskKey,
            $asset->originalName,
            $altText !== '' ? $altText : null,
            $asset->mime,
            $asset->extension,
            $asset->byteSize,
            $asset->width,
            $asset->height,
            $asset->checksum,
            $asset->createdAt,
            $asset->folderId,
            $asset->focusX,
            $asset->focusY,
        );
        $this->repository->updateAlt($updated);

        return $updated;
    }

    public function updateFocus(MediaAsset $asset, float $focusX, float $focusY): MediaAsset
    {
        if (!$asset->isImage()) {
            throw new InvalidArgumentException('media.focus_images_only');
        }
        $updated = new MediaAsset(
            $asset->id,
            $asset->siteId,
            $asset->diskKey,
            $asset->originalName,
            $asset->altText,
            $asset->mime,
            $asset->extension,
            $asset->byteSize,
            $asset->width,
            $asset->height,
            $asset->checksum,
            $asset->createdAt,
            $asset->folderId,
            $focusX,
            $focusY,
        );
        $this->repository->updateFocus($updated);

        return $updated;
    }

    public function delete(MediaAsset $asset): bool
    {
        $variants = $this->repository->variantsFor($asset->id);
        if (!$this->repository->softDelete($asset->id, $asset->siteId)) {
            return false;
        }

        foreach ($variants as $variant) {
            $path = $this->absolutePath($variant->diskKey);
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->repository->deleteVariants($asset->id);
        $absolute = $this->absolutePath($asset->diskKey);
        if (is_file($absolute)) {
            @unlink($absolute);
        }

        return true;
    }

    public function read(MediaAsset $asset): string
    {
        return $this->readDiskKey($asset->diskKey);
    }

    public function readVariant(MediaVariant $variant): string
    {
        return $this->readDiskKey($variant->diskKey);
    }

    public function regenerateVariants(MediaAsset $asset): void
    {
        if (!$asset->isImage()) {
            throw new InvalidArgumentException('media.variants_images_only');
        }
        $absolute = $this->absolutePath($asset->diskKey);
        if (!is_file($absolute)) {
            throw new RuntimeException('media.missing');
        }
        $this->generateVariants($asset, $absolute);
    }

    private function readDiskKey(string $diskKey): string
    {
        $absolute = $this->absolutePath($diskKey);
        $contents = file_get_contents($absolute);
        if ($contents === false) {
            throw new RuntimeException('media.missing');
        }

        return $contents;
    }

    private function absolutePath(string $diskKey): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $diskKey);
    }

    private function generateVariants(MediaAsset $asset, string $absoluteOriginal): void
    {
        if (!$asset->isImage()) {
            return;
        }
        $image = @imagecreatefromstring((string) file_get_contents($absoluteOriginal));
        if ($image === false) {
            return;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);

        // thumb
        $thumbW = $srcW;
        $thumbH = $srcH;
        if ($srcW > self::THUMB_MAX) {
            $thumbW = self::THUMB_MAX;
            $thumbH = (int) round($srcH * (self::THUMB_MAX / $srcW));
        }
        $thumbW = max(1, $thumbW);
        $thumbH = max(1, $thumbH);
        $thumb = imagecreatetruecolor($thumbW, $thumbH);
        if ($thumb !== false) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbW, $thumbH, $srcW, $srcH);
            $thumbKey = $this->variantKey($asset, 'thumb', 'jpg');
            $thumbPath = $this->absolutePath($thumbKey);
            if (imagejpeg($thumb, $thumbPath, 82)) {
                $this->repository->saveVariant(new MediaVariant(
                    Uuid::v7(),
                    $asset->id,
                    'thumb',
                    $thumbKey,
                    'image/jpeg',
                    $thumbW,
                    $thumbH,
                    (int) filesize($thumbPath),
                ));
            }
            imagedestroy($thumb);
        }

        // webp (skip if original already webp and small enough — still store for consistent URLs)
        if (function_exists('imagewebp')) {
            $webpKey = $this->variantKey($asset, 'webp', 'webp');
            $webpPath = $this->absolutePath($webpKey);
            if (imagewebp($image, $webpPath, 80)) {
                $this->repository->saveVariant(new MediaVariant(
                    Uuid::v7(),
                    $asset->id,
                    'webp',
                    $webpKey,
                    'image/webp',
                    $srcW,
                    $srcH,
                    (int) filesize($webpPath),
                ));
            }
        }

        imagedestroy($image);
    }

    private function variantKey(MediaAsset $asset, string $handle, string $ext): string
    {
        $dir = dirname($asset->diskKey);

        return $dir . '/' . $asset->id->value . '.' . $handle . '.' . $ext;
    }

    private function persistSafely(string $mime, string $binary, string $absolute): string
    {
        if ($mime === 'application/pdf') {
            file_put_contents($absolute, $binary);

            return $binary;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new InvalidArgumentException('media.corrupt');
        }

        $ok = match ($mime) {
            'image/png' => imagepng($image, $absolute, 6),
            'image/jpeg' => imagejpeg($image, $absolute, 85),
            'image/gif' => imagegif($image, $absolute),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $absolute, 80) : imagepng($image, $absolute, 6),
            default => false,
        };
        imagedestroy($image);
        if ($ok !== true) {
            throw new RuntimeException('media.encode');
        }

        $stored = file_get_contents($absolute);
        if ($stored === false) {
            throw new RuntimeException('media.missing');
        }

        return $stored;
    }
}
