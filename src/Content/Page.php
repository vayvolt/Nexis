<?php

declare(strict_types=1);

namespace Nexis\Content;

use DateTimeImmutable;
use Nexis\Auth\UserId;
use Nexis\Builder\RevisionId;
use Nexis\Builder\SnapshotId;
use Nexis\Site\SiteId;

final class Page
{
    public function __construct(
        public private(set) PageId $id,
        public private(set) SiteId $siteId,
        public private(set) string $translationGroupId,
        public private(set) string $locale,
        public private(set) string $slug,
        public private(set) string $path,
        public private(set) string $title,
        public private(set) ?string $bodyText,
        public private(set) PageStatus $status,
        public private(set) ?RevisionId $publishedRevisionId = null,
        public private(set) ?SnapshotId $publishedSnapshotId = null,
        public private(set) ?string $searchText = null,
        public private(set) ?string $metaTitle = null,
        public private(set) ?string $metaDescription = null,
        public private(set) string $robots = 'index,follow',
        public private(set) ?DateTimeImmutable $scheduledAt = null,
        public private(set) ?UserId $scheduledBy = null,
        public private(set) string $type = PageType::PAGE,
        public private(set) ?DateTimeImmutable $updatedAt = null,
        public private(set) ?DateTimeImmutable $publishedAt = null,
        public private(set) ?string $authorName = null,
        public private(set) ?DateTimeImmutable $unpublishAt = null,
        public private(set) ?UserId $unpublishBy = null,
        public private(set) ?string $focusKeyword = null,
    ) {
    }

    public bool $isPost {
        get => $this->type === PageType::POST;
    }

    public bool $isProduct {
        get => $this->type === PageType::PRODUCT;
    }

    public bool $isPublished {
        get => $this->status === PageStatus::Published;
    }

    public bool $isScheduled {
        get => $this->status === PageStatus::Scheduled;
    }

    public bool $isInReview {
        get => $this->status === PageStatus::InReview;
    }

    public string $documentTitle {
        get => ($this->metaTitle !== null && $this->metaTitle !== '') ? $this->metaTitle : $this->title;
    }

    public string $documentDescription {
        get => $this->metaDescription ?? '';
    }

    /**
     * Best available public date: snapshot publish time, else last update.
     */
    public ?DateTimeImmutable $displayDate {
        get => $this->publishedAt ?? $this->updatedAt;
    }
}
