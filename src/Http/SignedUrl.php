<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Kernel\Config;
use RuntimeException;

final class SignedUrl
{
    public function __construct(
        private Config $config,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function sign(string $path, array $params, int $ttlSeconds = 900): string
    {
        $params['expires'] = (string) (time() + $ttlSeconds);
        ksort($params);
        $params['sig'] = $this->signature($path, $params);

        return $path . '?' . http_build_query($params);
    }

    /**
     * @param array<string, string> $params
     */
    public function assertValid(string $path, array $params): void
    {
        $sig = $params['sig'] ?? '';
        $expires = $params['expires'] ?? '';
        if (!is_string($sig) || $sig === '' || !is_string($expires) || !ctype_digit($expires)) {
            throw new RuntimeException('preview.invalid');
        }
        if ((int) $expires < time()) {
            throw new RuntimeException('preview.expired');
        }
        $check = $params;
        unset($check['sig']);
        ksort($check);
        $expected = $this->signature($path, $check);
        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('preview.invalid');
        }
    }

    /**
     * @param array<string, string> $params
     */
    private function signature(string $path, array $params): string
    {
        $key = (string) $this->config->get('app.key', '');
        if ($key === '') {
            throw new RuntimeException('APP_KEY fehlt für signierte URLs.');
        }
        $payload = $path . '?' . http_build_query($params);

        return hash_hmac('sha256', $payload, $key);
    }
}
