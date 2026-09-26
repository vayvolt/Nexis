<?php

declare(strict_types=1);

namespace Nexis\Site;

enum LocaleUrlStrategy: string
{
    case Prefix = 'prefix';
    case Domain = 'domain';
    case None = 'none';
}
