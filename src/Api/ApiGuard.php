<?php

declare(strict_types=1);

namespace Nexis\Api;

use Nexis\Auth\SitePolicy;
use Nexis\Auth\User;
use Nexis\Http\Csrf;
use Nexis\Http\JsonBody;
use Nexis\Http\Middleware\ApiTokenAuthMiddleware;
use Nexis\Http\ResponseFactory;
use Nexis\I18n\PublicUi;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shared request plumbing for the API controllers: site/user resolution,
 * permission checks (including token scopes) and error responses.
 */
final class ApiGuard
{
    public function __construct(
        private ResponseFactory $responses,
        private SiteRepository $sites,
        private SitePolicy $policy,
        private PublicUi $ui,
    ) {
    }

    /**
     * @return Site|ResponseInterface Site, or a 503 envelope when nothing is installed
     */
    public function site(ServerRequestInterface $request): Site|ResponseInterface
    {
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            return $site;
        }
        $installed = $this->sites->installed();
        if ($installed instanceof Site) {
            return $installed;
        }

        return $this->error($request, ApiError::SITE_UNAVAILABLE, 'api.error.site_unavailable', 503);
    }

    /**
     * Site plus authenticated user. ApiTokenAuthMiddleware has already rejected
     * anonymous requests; this keeps the controllers type-safe.
     *
     * @return array{User, Site}|ResponseInterface
     */
    public function context(ServerRequestInterface $request): array|ResponseInterface
    {
        $site = $this->site($request);
        if ($site instanceof ResponseInterface) {
            return $site;
        }
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            return $this->unauthorized($request);
        }

        return [$user, $site];
    }

    /**
     * Effective permission check: a token with scopes may only use the listed
     * permissions, and the owning user must still hold them on the site.
     */
    public function assertApiPermission(
        ServerRequestInterface $request,
        Site $site,
        string $permission,
    ): ?ResponseInterface {
        return $this->assertApiPermissionAny($request, $site, [$permission]);
    }

    /**
     * @param list<string> $permissions Any one of them is sufficient.
     */
    public function assertApiPermissionAny(
        ServerRequestInterface $request,
        Site $site,
        array $permissions,
    ): ?ResponseInterface {
        if (!$request->getAttribute('user') instanceof User) {
            return $this->unauthorized($request);
        }
        if ($this->allowsAny($request, $site, $permissions)) {
            return null;
        }

        return $this->error(
            $request,
            ApiError::FORBIDDEN,
            'api.error.forbidden',
            403,
            ['requiredPermissions' => array_values($permissions)],
        );
    }

    /**
     * Same check without a response, for partial updates whose required rights
     * depend on the fields actually sent.
     *
     * @param list<string> $permissions
     */
    public function allowsAny(ServerRequestInterface $request, Site $site, array $permissions): bool
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            return false;
        }

        $token = $request->getAttribute(ApiTokenAuthMiddleware::TOKEN_ATTRIBUTE);
        foreach ($permissions as $permission) {
            if ($token instanceof ApiToken && !$token->allowsScope($permission)) {
                continue;
            }
            if ($this->policy->can($user, $site, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Request body of the write endpoints.
     *
     * @return array<string, mixed>|ResponseInterface 400 envelope for malformed JSON
     */
    public function json(ServerRequestInterface $request): array|ResponseInterface
    {
        $body = JsonBody::decode($request);
        if ($body === null) {
            return $this->error($request, ApiError::INVALID_JSON, 'api.error.invalid_json', 400);
        }
        // Session clients may carry the CSRF token in the body; it is no payload field.
        unset($body[Csrf::FIELD]);

        return $body;
    }

    public function notFound(ServerRequestInterface $request, string $code = ApiError::NOT_FOUND): ResponseInterface
    {
        return $this->error($request, $code, 'api.error.not_found', 404);
    }

    /**
     * @param array<string, mixed> $details
     */
    public function invalid(
        ServerRequestInterface $request,
        string $messageKey = 'api.error.invalid_request',
        array $details = [],
    ): ResponseInterface {
        return $this->error($request, ApiError::INVALID_REQUEST, $messageKey, 422, $details);
    }

    public function unauthorized(ServerRequestInterface $request): ResponseInterface
    {
        return $this->error($request, ApiError::UNAUTHORIZED, 'api.error.unauthorized', 401)
            ->withHeader('WWW-Authenticate', 'Bearer');
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public function ok(ServerRequestInterface $request, array $data, array $meta = []): ResponseInterface
    {
        return $this->responses->json(ApiJson::payload($request, $data, $meta));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function created(ServerRequestInterface $request, array $data, string $location = ''): ResponseInterface
    {
        $response = $this->responses->json(ApiJson::payload($request, $data), 201);

        return $location === '' ? $response : $response->withHeader('Location', $location);
    }

    /**
     * @param list<mixed> $items
     * @param array<string, mixed> $meta
     */
    public function okList(ServerRequestInterface $request, array $items, array $meta = []): ResponseInterface
    {
        return $this->responses->json(ApiJson::payload($request, $items, $meta));
    }

    /**
     * @param array<string, mixed> $details
     */
    public function error(
        ServerRequestInterface $request,
        string $code,
        string $messageKey,
        int $status,
        array $details = [],
    ): ResponseInterface {
        return $this->responses->json(
            ApiError::payload($request, $code, $this->ui->getRequest($request, $messageKey), $details),
            $status,
        );
    }
}
