<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Http\PublicErrorRenderer;
use Nexis\Http\ResponseFactory;
use Nexis\I18n\PublicUi;
use Nexis\Kernel\Config;
use Nexis\Kernel\Nexis;
use Nexis\Site\Site;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Config $config,
        private LoggerInterface $logger,
        private ResponseFactory $responses,
        private PublicUi $ui,
        private PublicErrorRenderer $errors,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $requestId = (string) $request->getAttribute('request_id', '');
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'request_id' => $requestId,
                'path' => (string) $request->getUri()->getPath(),
            ]);

            if ($this->wantsJson($request)) {
                $details = new \stdClass();
                if ($this->config->debug()) {
                    $details = [
                        'type' => $exception::class,
                        'message' => $exception->getMessage(),
                    ];
                }

                return $this->responses->json([
                    'error' => [
                        'code' => 'http.internal',
                        'message' => $this->ui->getRequest($request, 'http.internal_error'),
                        'details' => $details,
                    ],
                    'requestId' => $requestId,
                ], 500)->withHeader('X-Request-Id', $requestId);
            }

            $path = (string) $request->getAttribute('path', '');
            if (str_starts_with($path, '/admin')) {
                return $this->adminHtmlError($request, $exception, $requestId);
            }

            $site = $request->getAttribute('site');
            $debug = $this->config->debug() ? $exception->getMessage() : null;

            return $this->errors->serverError(
                $request,
                $debug,
                $site instanceof Site ? $site : null,
            );
        }
    }

    private function wantsJson(ServerRequestInterface $request): bool
    {
        $path = (string) $request->getAttribute('path', $request->getUri()->getPath());
        if (str_starts_with($path, '/api/') || $path === '/health' || str_starts_with($path, '/health/')) {
            return true;
        }
        $accept = strtolower($request->getHeaderLine('Accept'));
        if ($accept === '') {
            return false;
        }

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    private function adminHtmlError(
        ServerRequestInterface $request,
        Throwable $exception,
        string $requestId,
    ): ResponseInterface {
        $basePath = (string) $request->getAttribute('base_path', '');
        $message = $this->ui->getRequest($request, 'http.internal_error');
        $detail = $this->config->debug() ? $exception->getMessage() : '';
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $home = $e(($basePath === '' ? '' : $basePath) . '/admin');
        $brand = $e(Nexis::brandUrl($basePath, Nexis::BRAND_ICON));
        $detailHtml = $detail !== ''
            ? '<p class="detail">' . $e($detail) . '</p>'
            : '';
        $req = $requestId !== ''
            ? '<p class="muted">Request-ID: <code>' . $e($requestId) . '</code></p>'
            : '';

        $html = '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>500 · Nexis</title>'
            . '<link rel="icon" type="image/svg+xml" href="' . $brand . '">'
            . '<style>'
            . 'body{font:16px/1.5 "Segoe UI",system-ui,sans-serif;background:#f4f1ea;color:#1c1917;margin:0;min-height:100vh;display:grid;place-items:center}'
            . '.card{width:min(28rem,92vw);background:#fff;border:1px solid #d6d3d1;border-radius:10px;padding:1.5rem 1.35rem}'
            . '.status{display:inline-block;font:700 .75rem/1 system-ui,sans-serif;letter-spacing:.04em;color:#9f1239;background:#fff1f2;border:1px solid #fecdd3;border-radius:999px;padding:.35rem .65rem;margin:0 0 .75rem}'
            . 'h1{margin:0 0 .5rem;font-size:1.35rem}'
            . 'p{margin:.35rem 0;color:#44403c}'
            . '.detail{font-family:ui-monospace,monospace;font-size:.85rem;background:#fafaf9;border:1px solid #e7e5e4;border-radius:6px;padding:.65rem;overflow:auto}'
            . '.muted{color:#78716c;font-size:.85rem}'
            . 'a.btn{display:inline-block;margin-top:1rem;background:#1b4d3e;color:#fff;text-decoration:none;border-radius:6px;padding:.55rem .9rem;font-weight:600}'
            . '</style></head><body><div class="card">'
            . '<span class="status">500</span>'
            . '<h1>' . $e($message) . '</h1>'
            . $detailHtml
            . $req
            . '<a class="btn" href="' . $home . '">Admin</a>'
            . '</div></body></html>';

        return $this->responses->html($html, 500)->withHeader('X-Request-Id', $requestId);
    }
}
