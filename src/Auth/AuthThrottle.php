<?php

declare(strict_types=1);

namespace Nexis\Auth;

/**
 * Durable login throttling per account and IP (file-backed, survives new sessions).
 */
final class AuthThrottle
{
    private const ACCOUNT_MAX = 8;
    private const IP_MAX = 40;
    private const LOCK_SECONDS = 900;

    public function __construct(
        private string $storagePath,
    ) {
    }

    public function assertNotLocked(string $email, string $ip): void
    {
        if ($this->lockedUntil($this->accountKey($email)) > time()
            || $this->lockedUntil($this->ipKey($ip)) > time()) {
            throw new \RuntimeException('auth.locked');
        }
    }

    public function recordFailure(string $email, string $ip): void
    {
        $this->hit($this->accountKey($email), self::ACCOUNT_MAX);
        $this->hit($this->ipKey($ip), self::IP_MAX);
    }

    public function clear(string $email, string $ip): void
    {
        $this->delete($this->accountKey($email));
    }

    private function accountKey(string $email): string
    {
        return 'account:' . hash('sha256', mb_strtolower(trim($email)));
    }

    private function ipKey(string $ip): string
    {
        return 'ip:' . hash('sha256', $ip !== '' ? $ip : '0.0.0.0');
    }

    private function hit(string $key, int $maxAttempts): void
    {
        $path = $this->path($key);
        $now = time();
        $data = $this->read($path);
        if (($data['locked_until'] ?? 0) > $now) {
            return;
        }
        $attempts = [];
        foreach ($data['attempts'] ?? [] as $ts) {
            if (is_int($ts) && $ts > $now - self::LOCK_SECONDS) {
                $attempts[] = $ts;
            }
        }
        $attempts[] = $now;
        $lockedUntil = 0;
        if (count($attempts) >= $maxAttempts) {
            $lockedUntil = $now + self::LOCK_SECONDS;
            $attempts = [];
        }
        $this->write($path, [
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ]);
    }

    private function lockedUntil(string $key): int
    {
        $data = $this->read($this->path($key));

        return (int) ($data['locked_until'] ?? 0);
    }

    /**
     * @return array{attempts?: list<int>, locked_until?: int}
     */
    private function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array{attempts: list<int>, locked_until: int} $data
     */
    private function write(string $path, array $data): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
    }

    private function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $key): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }
}
