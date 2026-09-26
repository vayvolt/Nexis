<?php

declare(strict_types=1);

namespace Nexis\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ResponseFactory
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function json(array $payload, int $status = 200): ResponseInterface
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $body = $this->streams->createStream($encoded);

        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($body);
    }

    public function empty(int $status = 204): ResponseInterface
    {
        return $this->responses->createResponse($status);
    }

    public function html(string $html, int $status = 200): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streams->createStream($html));
    }

    public function redirect(string $location, int $status = 302): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Location', $location);
    }

    public function file(string $binary, string $mime): ResponseInterface
    {
        return $this->responses->createResponse(200)
            ->withHeader('Content-Type', $mime)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withBody($this->streams->createStream($binary));
    }

    public function xml(string $xml, int $status = 200): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withBody($this->streams->createStream($xml));
    }

    public function text(string $body, string $contentType = 'text/plain; charset=utf-8', int $status = 200): ResponseInterface
    {
        return $this->responses->createResponse($status)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream($body));
    }
}
