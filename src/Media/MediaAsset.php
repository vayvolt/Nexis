<?php

declare(strict_types=1);

namespace Nexis\Media;

use Nexis\Site\SiteId;

final class MediaAsset
{
    public function __construct(
        public private(set) MediaId $id,
        public private(set) SiteId $siteId,
        public private(set) string $diskKey,
        public private(set) string $originalName,
        public private(set) ?string $altText,
        public private(set) string $mime,
        public private(set) string $extension,
        public private(set) int $byteSize,
        public private(set) ?int $width,
        public private(set) ?int $height,
        public private(set) string $checksum,
        public private(set) ?\DateTimeImmutable $createdAt = null,
        public private(set) ?MediaFolderId $folderId = null,
        public private(set) float $focusX = 50.0,
        public private(set) float $focusY = 50.0,
    ) {
        $this->focusX = self::clampFocus($focusX);
        $this->focusY = self::clampFocus($focusY);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function displayAlt(): string
    {
        if (is_string($this->altText) && $this->altText !== '') {
            return $this->altText;
        }

        return $this->originalName;
    }

    public function objectPositionCss(): string
    {
        return $this->focusX . '% ' . $this->focusY . '%';
    }

    public static function clampFocus(float $value): float
    {
        if ($value < 0.0) {
            return 0.0;
        }
        if ($value > 100.0) {
            return 100.0;
        }

        return round($value, 2);
    }

    public function humanSize(): string
    {
        $n = $this->byteSize;
        if ($n < 1024) {
            return $n . ' B';
        }
        if ($n < 1_048_576) {
            return round($n / 1024, 1) . ' KB';
        }

        return round($n / 1_048_576, 1) . ' MB';
    }
}
