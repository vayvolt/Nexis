<?php

declare(strict_types=1);

namespace Nexis\Http;

use RuntimeException;

final class Csrf
{
    public const SESSION_KEY = '_csrf';
    public const FIELD = '_csrf';

    public function __construct(
        private SessionStore $session,
    ) {
    }

    public function token(): string
    {
        $existing = $this->session->get(self::SESSION_KEY);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    public function assert(mixed $provided): void
    {
        $expected = $this->session->get(self::SESSION_KEY);
        if (!is_string($expected) || !is_string($provided) || !hash_equals($expected, $provided)) {
            throw new RuntimeException('csrf.invalid');
        }
    }
}
