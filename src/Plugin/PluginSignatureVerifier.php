<?php

declare(strict_types=1);

namespace Nexis\Plugin;

use Nexis\Kernel\Config;
use Psr\Log\LoggerInterface;

/**
 * Optional Ed25519 signature check for plugin packages.
 * When PLUGIN_TRUST_PUBLIC_KEY is empty, verification is skipped (local/dev).
 * When set, plugins with signature.ed25519 must verify; unsigned plugins are refused.
 */
final class PluginSignatureVerifier
{
    public function __construct(
        private Config $config,
        private LoggerInterface $logger,
    ) {
    }

    public function assertTrusted(PluginManifest $manifest): void
    {
        $publicKeyB64 = trim((string) $this->config->get('plugins.trust_public_key', ''));
        if ($publicKeyB64 === '') {
            return;
        }
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new \RuntimeException('ext-sodium fehlt für Plugin-Signaturprüfung.');
        }

        $sigFile = $manifest->directory . DIRECTORY_SEPARATOR . 'signature.ed25519';
        if (!is_file($sigFile)) {
            throw new \RuntimeException('Plugin-Signatur fehlt: ' . $manifest->id);
        }

        $signatureB64 = trim((string) file_get_contents($sigFile));
        $signature = base64_decode($signatureB64, true);
        $publicKey = base64_decode($publicKeyB64, true);
        if ($signature === false || $signature === '' || $publicKey === false || $publicKey === '') {
            throw new \RuntimeException('Plugin-Signatur oder Trust-Key ungültig.');
        }

        $payload = $this->canonicalPayload($manifest);
        if (!sodium_crypto_sign_verify_detached($signature, $payload, $publicKey)) {
            $this->logger->error('Plugin signature mismatch', ['plugin' => $manifest->id]);
            throw new \RuntimeException('Plugin-Signatur ungültig: ' . $manifest->id);
        }
    }

    private function canonicalPayload(PluginManifest $manifest): string
    {
        $pluginJson = $manifest->directory . DIRECTORY_SEPARATOR . 'plugin.json';
        $json = is_file($pluginJson) ? (string) file_get_contents($pluginJson) : '';

        return hash('sha256', $json, true);
    }
}
