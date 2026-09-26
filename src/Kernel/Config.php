<?php

declare(strict_types=1);

namespace Nexis\Kernel;

final class Config
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $values,
        public private(set) string $rootPath,
    ) {
    }

    public static function load(string $rootPath): self
    {
        return new self([
            'app' => [
                'env' => self::env('APP_ENV', 'production'),
                'debug' => filter_var(self::env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
                'url' => self::env('APP_URL', ''),
                'key' => self::env('APP_KEY', ''),
                'trusted_proxies' => self::env('TRUSTED_PROXIES', ''),
            ],
            'plugins' => [
                'trust_public_key' => self::env('PLUGIN_TRUST_PUBLIC_KEY', ''),
                'marketplace_url' => \Nexis\Plugin\MarketplaceClient::DIRECTORY_URL,
            ],
            'db' => [
                'host' => self::env('DB_HOST', '127.0.0.1'),
                'port' => self::env('DB_PORT', '3306'),
                'database' => self::env('DB_DATABASE', 'nexis'),
                'username' => self::env('DB_USERNAME', 'root'),
                'password' => self::env('DB_PASSWORD', ''),
            ],
            'mail' => [
                'transport' => self::env('MAIL_TRANSPORT', 'log'),
                'host' => self::env('MAIL_HOST', '127.0.0.1'),
                'port' => self::env('MAIL_PORT', '587'),
                'encryption' => self::env('MAIL_ENCRYPTION', 'tls'),
                'username' => self::env('MAIL_USERNAME', ''),
                'password' => self::env('MAIL_PASSWORD', ''),
                'from_address' => self::env('MAIL_FROM_ADDRESS', 'noreply@localhost'),
                'from_name' => self::env('MAIL_FROM_NAME', 'Nexis'),
            ],
            'media' => [
                'max_bytes' => max(1, (int) self::env('MEDIA_MAX_BYTES', '10485760')),
                'max_pixels' => max(1, (int) self::env('MEDIA_MAX_PIXELS', '25000000')),
            ],
        ], $rootPath);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cursor = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return $default;
            }
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    public function envName(): string
    {
        return (string) $this->get('app.env', 'production');
    }

    public function debug(): bool
    {
        return (bool) $this->get('app.debug', false);
    }

    /**
     * @return list<string>
     */
    public function trustedProxies(): array
    {
        $raw = trim((string) $this->get('app.trusted_proxies', ''));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $p): string => trim($p), $parts),
            static fn (string $p): bool => $p !== '',
        ));
    }

    /**
     * Path prefix from APP_URL (e.g. `/nexis`), or empty when the app is at domain root.
     */
    public function publicBasePath(): string
    {
        $url = trim((string) $this->get('app.url', ''));
        if ($url === '') {
            return '';
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            return '';
        }

        return rtrim($path, '/');
    }

    private static function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (!is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }
}
