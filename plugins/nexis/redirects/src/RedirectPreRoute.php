<?php

declare(strict_types=1);

namespace Nexis\Plugins\Redirects;

use Nexis\Http\ResponseFactory;
use Nexis\Http\Router;
use Nexis\Plugin\PreRouteHandler;
use Nexis\Site\SiteRepository;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RedirectPreRoute implements PreRouteHandler
{
    public function __construct(
        private PDO $pdo,
        private SiteRepository $sites,
        private ResponseFactory $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ?ResponseInterface
    {
        $site = $this->sites->installed();
        if ($site === null) {
            return null;
        }

        $path = Router::normalizePath((string) $request->getAttribute('path', '/'));
        $stmt = $this->pdo->prepare(
            'SELECT to_url, status_code FROM plugin_nexis_redirects
             WHERE site_id = :site_id AND from_path = :from_path
               AND (locale IS NULL OR locale = :locale)
             ORDER BY locale DESC
             LIMIT 1',
        );
        // locale unknown at pre-route; match path-only redirects (locale NULL) first via ORDER
        $stmt->execute([
            'site_id' => $site->id->value,
            'from_path' => $path,
            'locale' => $site->defaultLocale,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $to = (string) $row['to_url'];
        $code = (int) $row['status_code'];
        if ($code < 300 || $code > 399) {
            $code = 301;
        }

        return $this->responses->redirect($to, $code);
    }
}
