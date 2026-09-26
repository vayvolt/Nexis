<?php

declare(strict_types=1);

namespace Nexis\Media;

final class MediaVariant
{
    public function __construct(
        public private(set) string $id,
        public private(set) MediaId $assetId,
        public private(set) string $handle,
        public private(set) string $diskKey,
        public private(set) string $mime,
        public private(set) ?int $width,
        public private(set) ?int $height,
        public private(set) int $byteSize,
    ) {
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

    public function dimensionsLabel(): string
    {
        if ($this->width === null || $this->height === null) {
            return '';
        }

        return $this->width . '×' . $this->height;
    }
}
