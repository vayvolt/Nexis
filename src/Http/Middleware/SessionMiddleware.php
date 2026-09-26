<?php

declare(strict_types=1);

namespace Nexis\Http\Middleware;

use Nexis\Auth\LoginService;
use Nexis\Http\Csrf;
use Nexis\Http\RequestSecurity;
use Nexis\Http\SessionStore;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionStore $session,
        private LoginService $login,
        private Csrf $csrf,
        private RequestSecurity $security,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $basePath = (string) $request->getAttribute('base_path', '');
        $cookiePath = $basePath === '' ? '/' : $basePath;
        $this->session->start($cookiePath, $this->security->isHttps($request));

        $request = $request
            ->withAttribute('user', $this->login->current())
            ->withAttribute('csrf', $this->csrf->token());

        return $handler->handle($request);
    }
}
