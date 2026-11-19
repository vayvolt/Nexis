<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\I18n\PublicUi;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Theme\ThemeService;
use Nexis\Theme\ThemeViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Themed public HTML error pages (404 / 403 / 500 / 503) with core fallbacks.
 */
final class PublicErrorRenderer
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private ThemeService $themes,
        private ThemeViewRenderer $themeViews,
        private ViewRenderer $views,
        private PublicUi $ui,
    ) {
    }

    public function notFound(
        ServerRequestInterface $request,
        string $message,
        ?Site $site = null,
        ?string $locale = null,
    ): ResponseInterface {
        return $this->render($request, 404, '404', [
            'message' => $message,
            'titleKey' => 'public.not_found.title',
            'headingKey' => 'public.not_found.page',
            'homeLabelKey' => 'public.error.home',
        ], $site, $locale);
    }

    public function forbidden(ServerRequestInterface $request, ?Site $site = null, ?string $locale = null): ResponseInterface
    {
        return $this->render($request, 403, 'error', [
            'message' => null,
            'messageKey' => 'public.error.forbidden',
            'titleKey' => 'public.error.forbidden_title',
            'headingKey' => 'public.error.forbidden_heading',
            'homeLabelKey' => 'public.error.home',
            'statusLabel' => '403',
        ], $site, $locale);
    }

    public function serverError(
        ServerRequestInterface $request,
        ?string $debugMessage = null,
        ?Site $site = null,
        ?string $locale = null,
    ): ResponseInterface {
        return $this->render($request, 500, 'error', [
            'message' => $debugMessage,
            'messageKey' => 'http.internal_error',
            'titleKey' => 'public.error.server_title',
            'headingKey' => 'public.error.server_heading',
            'homeLabelKey' => 'public.error.home',
            'statusLabel' => '500',
        ], $site, $locale);
    }

    public function maintenance(
        ServerRequestInterface $request,
        string $message,
        ?Site $site = null,
        ?string $locale = null,
    ): ResponseInterface {
        return $this->render($request, 503, 'maintenance', [
            'message' => $message,
            'titleKey' => 'public.maintenance.title',
            'headingKey' => 'public.maintenance.heading',
            'homeLabelKey' => 'public.error.home',
            'statusLabel' => '503',
            'retryAfter' => 3600,
        ], $site, $locale);
    }

    /**
     * @param array{
     *   message: ?string,
     *   messageKey?: string,
     *   titleKey: string,
     *   headingKey: string,
     *   homeLabelKey: string,
     *   statusLabel?: string,
     *   retryAfter?: int
     * } $opts
     */
    private function render(
        ServerRequestInterface $request,
        int $status,
        string $templateName,
        array $opts,
        ?Site $site,
        ?string $locale,
    ): ResponseInterface {
        $requestId = (string) $request->getAttribute('request_id', '');
        $basePath = (string) $request->getAttribute('base_path', '');
        if (!$site instanceof Site) {
            $attr = $request->getAttribute('site');
            $site = $attr instanceof Site ? $attr : $this->sites->installed();
        }
        if ($locale === null || $locale === '') {
            $locale = $site instanceof Site ? $site->defaultLocale : 'de';
        }

        $translator = $this->ui->translator($locale);
        $t = static fn (string $key, array $replace = [], ?string $default = null): string => $translator->get($key, $replace, $default);
        $uiLocale = $translator->locale();

        $message = $opts['message'];
        if ($message === null || $message === '') {
            $message = $t($opts['messageKey'] ?? 'http.internal_error');
        }

        $title = $t($opts['titleKey']);
        $heading = $t($opts['headingKey']);
        $homeLabel = $t($opts['homeLabelKey']);
        $homeUrl = $basePath === '' ? '/' : $basePath . '/';
        $statusLabel = (string) ($opts['statusLabel'] ?? (string) $status);

        $data = [
            'message' => $message,
            'requestId' => $requestId,
            't' => $t,
            'uiLocale' => $uiLocale,
            'locale' => $locale,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'errorTitle' => $title,
            'errorHeading' => $heading,
            'notFoundTitle' => $title,
            'notFoundHeading' => $heading,
            'homeUrl' => $homeUrl,
            'homeLabel' => $homeLabel,
            'basePath' => $basePath,
            'site' => $site,
        ];

        if ($site instanceof Site) {
            try {
                $theme = $this->themes->resolveFor($site, $basePath);
                $template = $this->themes->resolveTemplate($theme['manifest'], $templateName);
                $html = $this->themeViews->renderFile($template, $data + [
                    'cssVariables' => $theme['cssVariables'],
                    'themeCssUrl' => $theme['themeCssUrl'],
                ]);
                $response = $this->responses->html($html, $status);
                if (isset($opts['retryAfter'])) {
                    $response = $response->withHeader('Retry-After', (string) (int) $opts['retryAfter']);
                }

                return $response->withHeader('X-Request-Id', $requestId);
            } catch (Throwable) {
            }
        }

        $view = match ($templateName) {
            'maintenance' => 'public.maintenance',
            '404' => 'public.not-found',
            default => 'public.error',
        };

        try {
            $html = $this->views->render($view, $data);
        } catch (Throwable) {
            $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeLocale = htmlspecialchars($uiLocale, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = '<!DOCTYPE html><html lang="' . $safeLocale . '"><body><h1>' . $safeTitle
                . '</h1><p>' . $safeMessage . '</p></body></html>';
        }

        $response = $this->responses->html($html, $status);
        if (isset($opts['retryAfter'])) {
            $response = $response->withHeader('Retry-After', (string) (int) $opts['retryAfter']);
        }

        return $response->withHeader('X-Request-Id', $requestId);
    }
}
