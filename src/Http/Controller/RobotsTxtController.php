<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Http\ResponseFactory;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RobotsTxtController
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $basePath = rtrim((string) $request->getAttribute('base_path', ''), '/');
        $host = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        $port = $request->getUri()->getPort();
        if ($port !== null && !in_array($port, [80, 443], true)) {
            $host .= ':' . $port;
        }
        $origin = $host . $basePath;

        $lines = [
            'User-agent: *',
            'Allow: ' . ($basePath === '' ? '/' : $basePath . '/'),
            'Disallow: ' . $basePath . '/admin',
            'Disallow: ' . $basePath . '/preview',
            'Disallow: ' . $basePath . '/account',
            'Disallow: ' . $basePath . '/install',
        ];
        if ($this->sites->installed() !== null) {
            $lines[] = 'Sitemap: ' . $origin . '/sitemap.xml';
        }
        $lines[] = '';

        return $this->responses->text(implode("\n", $lines))
            ->withHeader('Cache-Control', 'public, max-age=300');
    }
}
