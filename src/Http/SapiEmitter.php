<?php

declare(strict_types=1);

namespace Nexis\Http;

use Psr\Http\Message\ResponseInterface;

final class SapiEmitter
{
    public function emit(ResponseInterface $response, string $method = 'GET'): void
    {
        $status = $response->getStatusCode();
        http_response_code($status);

        foreach ($response->getHeaders() as $name => $values) {
            $replace = true;
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), $replace, $status);
                $replace = false;
            }
        }

        if ($status === 204 || $status === 304 || $status < 200 || strtoupper($method) === 'HEAD') {
            return;
        }

        echo (string) $response->getBody();
    }
}
