<?php

declare(strict_types=1);

namespace Nexis\Http;

final class NativeSessionStore implements SessionStore
{
    public function __construct(
        private string $savePath,
    ) {
    }

    public function start(string $cookiePath, bool $secure = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0775, true);
        }

        session_name('nexis_session');
        session_save_path($this->savePath);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookiePath === '' ? '/' : $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function flash(string $key, mixed $value): void
    {
        $bag = $this->get('_flash', []);
        if (!is_array($bag)) {
            $bag = [];
        }
        $bag[$key] = $value;
        $this->set('_flash', $bag);
    }

    public function pullFlash(string $key, mixed $default = null): mixed
    {
        $bag = $this->get('_flash', []);
        if (!is_array($bag) || !array_key_exists($key, $bag)) {
            return $default;
        }
        $value = $bag[$key];
        unset($bag[$key]);
        if ($bag === []) {
            $this->remove('_flash');
        } else {
            $this->set('_flash', $bag);
        }

        return $value;
    }
}
