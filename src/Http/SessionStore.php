<?php

declare(strict_types=1);

namespace Nexis\Http;

interface SessionStore
{
    public function start(string $cookiePath, bool $secure = false): void;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    public function regenerate(): void;

    /** Store a one-shot value (PRG / checkout notices). */
    public function flash(string $key, mixed $value): void;

    /** Read and clear a flash value. */
    public function pullFlash(string $key, mixed $default = null): mixed;
}
