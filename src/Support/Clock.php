<?php

declare(strict_types=1);

namespace Nexis\Support;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
