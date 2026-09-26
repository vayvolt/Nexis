<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Kernel\Config;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves HTTPS and trusted-proxy headers for cookie/security decisions.
 */
final class RequestSecurity
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function isHttps(ServerRequestInterface $request): bool
    {
        $server = $request->getServerParams();
        $https = $server['HTTPS'] ?? null;
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        if ($request->getUri()->getScheme() === 'https') {
            return true;
        }

        $appUrl = strtolower((string) $this->config->get('app.url', ''));
        if (str_starts_with($appUrl, 'https://')) {
            return true;
        }

        if (!$this->isFromTrustedProxy($request)) {
            return false;
        }

        $forwarded = $server['HTTP_X_FORWARDED_PROTO'] ?? '';
        if (is_string($forwarded) && $forwarded !== '') {
            $first = strtolower(trim(explode(',', $forwarded)[0]));

            return $first === 'https';
        }

        return false;
    }

    public function isFromTrustedProxy(ServerRequestInterface $request): bool
    {
        $trusted = $this->config->trustedProxies();
        if ($trusted === []) {
            return false;
        }

        $remote = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        if (!is_string($remote) || $remote === '') {
            return false;
        }

        if (in_array('*', $trusted, true)) {
            return true;
        }

        return in_array($remote, $trusted, true);
    }

    public function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();
        $remote = $server['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!is_string($remote) || $remote === '') {
            $remote = '0.0.0.0';
        }

        if (!$this->isFromTrustedProxy($request)) {
            return $remote;
        }

        $forwarded = $server['HTTP_X_FORWARDED_FOR'] ?? '';
        if (is_string($forwarded) && $forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);
            if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $remote;
    }

    /**
     * True when Origin (or Referer fallback) matches the request host.
     * Rejects cross-site form posts; allows missing Origin only with same-host Referer.
     */
    public function isSameSiteFormPost(ServerRequestInterface $request): bool
    {
        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin !== '') {
            return $this->urlMatchesRequestHost($origin, $request);
        }

        $referer = trim($request->getHeaderLine('Referer'));
        if ($referer === '') {
            return false;
        }

        return $this->urlMatchesRequestHost($referer, $request);
    }

    /**
     * Returns a same-host Referer URL suitable for redirects, or null.
     */
    public function sameSiteReferer(ServerRequestInterface $request): ?string
    {
        $referer = trim($request->getHeaderLine('Referer'));
        if ($referer === '' || !$this->urlMatchesRequestHost($referer, $request)) {
            return null;
        }

        return $referer;
    }

    public function urlMatchesRequestHost(string $url, ServerRequestInterface $request): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host']) || !is_string($parts['host']) || $parts['host'] === '') {
            return false;
        }

        $requestHost = strtolower($request->getUri()->getHost());
        $urlHost = strtolower($parts['host']);
        if ($urlHost !== $requestHost) {
            return false;
        }

        $requestPort = $request->getUri()->getPort();
        $urlPort = isset($parts['port']) && is_int($parts['port']) ? $parts['port'] : null;
        $scheme = strtolower((string) ($parts['scheme'] ?? $request->getUri()->getScheme()));
        if ($urlPort === null) {
            $urlPort = $scheme === 'https' ? 443 : 80;
        }
        $effectiveRequestPort = $requestPort ?? ($request->getUri()->getScheme() === 'https' ? 443 : 80);

        return $urlPort === $effectiveRequestPort;
    }

    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return true;
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        return str_ends_with($host, '.local') || str_ends_with($host, '.test');
    }
}
