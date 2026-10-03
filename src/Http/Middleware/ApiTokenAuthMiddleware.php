<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Api\ApiError;
use Nexis\Api\ApiTokenStore;
use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Auth\UserRepository;
use Nexis\Http\BearerToken;
use Nexis\Http\ResponseFactory;
use Nexis\I18n\PublicUi;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Nexis\Support\Clock;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Authenticates `/api/v1/admin/*` via session user or `Authorization: Bearer nx_…`
 * and answers with JSON instead of the HTML redirects of RequireAuthMiddleware.
 * `/api/v1/public/*` stays anonymous.
 */
final class ApiTokenAuthMiddleware implements MiddlewareInterface
{
    public const TOKEN_ATTRIBUTE = 'api_token';

    private const ADMIN_PREFIX = '/api/v1/admin';

    public function __construct(
        private ResponseFactory $responses,
        private ApiTokenStore $tokens,
        private UserRepository $users,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private PublicUi $ui,
        private Clock $clock,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = (string) $request->getAttribute('path', $request->getUri()->getPath());
        if ($path !== self::ADMIN_PREFIX && !str_starts_with($path, self::ADMIN_PREFIX . '/')) {
            return $handler->handle($request);
        }

        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            $site = $this->sites->installed();
        }
        if (!$site instanceof Site) {
            return $this->error($request, ApiError::SITE_UNAVAILABLE, 'api.error.site_unavailable', 503);
        }

        $user = $request->getAttribute('user');
        $plaintext = BearerToken::fromRequest($request);
        if ($plaintext !== null) {
            $token = $this->tokens->findByPlaintext($plaintext);
            if ($token === null
                || !$token->isUsableAt($this->clock->now())
                || !$token->siteId->equals($site->id)
            ) {
                return $this->error($request, ApiError::UNAUTHORIZED, 'api.error.unauthorized', 401);
            }
            $user = $this->users->findById($token->userId);
            if (!$user instanceof User) {
                return $this->error($request, ApiError::UNAUTHORIZED, 'api.error.unauthorized', 401);
            }
            $this->tokens->touchLastUsed($token->id);
            $request = $request
                ->withAttribute('user', $user)
                ->withAttribute(self::TOKEN_ATTRIBUTE, $token);
        }

        if (!$user instanceof User) {
            return $this->error($request, ApiError::UNAUTHORIZED, 'api.error.unauthorized', 401);
        }
        if (!$this->policy->canAccessCms($user, $site)) {
            return $this->error($request, ApiError::FORBIDDEN, 'api.error.forbidden', 403);
        }

        return $handler->handle($request);
    }

    private function error(
        ServerRequestInterface $request,
        string $code,
        string $messageKey,
        int $status,
    ): ResponseInterface {
        $response = $this->responses->json(
            ApiError::payload($request, $code, $this->ui->getRequest($request, $messageKey)),
            $status,
        );

        return $status === 401 ? $response->withHeader('WWW-Authenticate', 'Bearer') : $response;
    }
}
