<?php

declare(strict_types=1);

namespace Nexis\Security;

use Nexis\Kernel\Config;
use RuntimeException;

/**
 * Authenticated encryption for site_settings secrets (libsodium secretbox).
 */
final class SecretBox
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->keyBytes();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);

        return 'nx1:' . base64_encode($nonce . $cipher);
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, 'nx1:')) {
            throw new RuntimeException('Unbekanntes Ciphertext-Format.');
        }
        $raw = base64_decode(substr($payload, 4), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Ciphertext ungültig.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->keyBytes());
        if ($plain === false) {
            throw new RuntimeException('Entschlüsselung fehlgeschlagen (APP_KEY?).');
        }

        return $plain;
    }

    /**
     * Keys that should be stored encrypted at rest.
     * Avoids false positives like auth.password_reset_enabled.
     */
    public static function isSecretKey(string $key): bool
    {
        $key = strtolower($key);
        if (preg_match('/(^|[._\-])(secret|token|api_?key|oauth|client_secret)([._\-]|$)/', $key) === 1) {
            return true;
        }

        return str_ends_with($key, '.password')
            || str_ends_with($key, '_password')
            || str_ends_with($key, '.password_hash');
    }

    private function keyBytes(): string
    {
        $raw = (string) $this->config->get('app.key', '');
        if ($raw === '' || $raw === 'change-me-to-a-long-random-string') {
            throw new RuntimeException('APP_KEY fehlt oder ist unsicher – Verschlüsselung nicht möglich.');
        }
        if (preg_match('/^[0-9a-f]{64}$/i', $raw) === 1) {
            $bin = hex2bin($raw);
            if (is_string($bin) && strlen($bin) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $bin;
            }
        }

        return hash('sha256', $raw, true);
    }
}
