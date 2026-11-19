<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Media\MediaId;
use Nexis\Site\SiteId;

final readonly class MediaUploaded
{
    public function __construct(
        public SiteId $siteId,
        public MediaId $mediaId,
        public string $mime,
        public string $diskKey,
        public string $originalName,
    ) {
    }
}
