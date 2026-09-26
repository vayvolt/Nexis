<?php

declare(strict_types=1);

namespace Nexis\Event;

use Nexis\Site\Site;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Fired once per HTTP request after the installed site is resolved.
 */
final readonly class SiteResolved
{
    public function __construct(
        public Site $site,
        public ServerRequestInterface $request,
    ) {
    }
}
