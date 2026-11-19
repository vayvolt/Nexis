<?php

declare(strict_types=1);

namespace Nexis\Content;

enum PageStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
