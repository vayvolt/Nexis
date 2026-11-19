<?php

declare(strict_types=1);

namespace Nexis\Http;

final readonly class Route
{
    /**
     * @param callable(\Psr\Http\Message\ServerRequestInterface): \Psr\Http\Message\ResponseInterface $handler
     */
    public function __construct(
        public string $method,
        public string $pattern,
        public mixed $handler,
    ) {
    }
}
