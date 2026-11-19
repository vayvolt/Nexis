<?php

declare(strict_types=1);

namespace Nexis\Http;

interface CspContributor
{
    public function contribute(ContentSecurityPolicy $csp): void;
}
