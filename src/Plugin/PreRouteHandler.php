<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface PreRouteHandler
{
    public function handle(ServerRequestInterface $request): ?ResponseInterface;
}
