<?php

declare(strict_types=1);

namespace Nexis\Auth;

use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Nexis\Http\SessionStore;
use Nexis\Kernel\Config;
use Nexis\Kernel\Nexis;
use RuntimeException;

/**
 * Thin wrapper around lbuchs/webauthn for passkey register/assert.
 */
final class WebAuthnService
{
    public function __construct(
        private PdoWebAuthnCredentialRepository $credentials,
        private SessionStore $session,
        private Config $config,
    ) {
    }

    public function hasCredentials(UserId $userId): bool
    {
        return $this->credentials->countForUser($userId) > 0;
    }

    /**
     * @return list<array{id: string, name: string, created_at: string, last_used_at: ?string}>
     */
    public function listCredentials(UserId $userId): array
    {
        $out = [];
        foreach ($this->credentials->listForUser($userId) as $row) {
            $out[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'created_at' => $row['created_at'],
                'last_used_at' => $row['last_used_at'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function beginRegistration(User $user): array
    {
        $webauthn = $this->client();
        $exclude = [];
        foreach ($this->credentials->listForUser($user->id) as $row) {
            $exclude[] = base64_decode($row['credential_id'], true) ?: $row['credential_id'];
        }
        $args = $webauthn->getCreateArgs(
            $user->id->value,
            $user->email,
            $user->displayName,
            60,
            false,
            'preferred',
            null,
            $exclude,
        );
        $challenge = $webauthn->getChallenge();
        $this->session->set('webauthn_create_challenge', $this->bufferToB64($challenge));

        return json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishRegistration(User $user, string $clientDataJSON, string $attestationObject, string $name = 'Passkey'): void
    {
        $challengeB64 = $this->session->get('webauthn_create_challenge');
        $this->session->remove('webauthn_create_challenge');
        if (!is_string($challengeB64) || $challengeB64 === '') {
            throw new RuntimeException('auth.webauthn_challenge');
        }
        $webauthn = $this->client();
        try {
            $data = $webauthn->processCreate(
                $this->b64ToBinary($clientDataJSON),
                $this->b64ToBinary($attestationObject),
                $this->b64ToBuffer($challengeB64),
                false,
                true,
                false,
            );
        } catch (WebAuthnException $e) {
            throw new RuntimeException('auth.webauthn_register', 0, $e);
        }

        $credentialId = base64_encode((string) $data->credentialId);
        $publicKey = (string) $data->credentialPublicKey;
        $name = trim($name);
        if ($name === '') {
            $name = 'Passkey';
        }
        $this->credentials->save($user->id, $credentialId, $publicKey, (int) ($data->signatureCounter ?? 0), $name);
    }

    /**
     * @return array<string, mixed>
     */
    public function beginAssertion(?UserId $userId = null): array
    {
        $webauthn = $this->client();
        $ids = [];
        if ($userId !== null) {
            foreach ($this->credentials->listForUser($userId) as $row) {
                $decoded = base64_decode($row['credential_id'], true);
                $ids[] = $decoded !== false ? $decoded : $row['credential_id'];
            }
        }
        $args = $webauthn->getGetArgs($ids, 60, true, true, true, true, true, 'preferred');
        $challenge = $webauthn->getChallenge();
        $this->session->set('webauthn_get_challenge', $this->bufferToB64($challenge));
        if ($userId !== null) {
            $this->session->set('webauthn_get_user_id', $userId->value);
        }

        return json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    public function finishAssertion(
        string $credentialIdB64,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
    ): UserId {
        $challengeB64 = $this->session->get('webauthn_get_challenge');
        $this->session->remove('webauthn_get_challenge');
        $this->session->remove('webauthn_get_user_id');
        if (!is_string($challengeB64) || $challengeB64 === '') {
            throw new RuntimeException('auth.webauthn_challenge');
        }

        $stored = $this->credentials->findByCredentialId($credentialIdB64);
        if ($stored === null) {
            // also try raw id if browser sent binary-as-b64 differently
            $alt = base64_encode($this->b64ToBinary($credentialIdB64));
            $stored = $this->credentials->findByCredentialId($alt);
        }
        if ($stored === null) {
            throw new RuntimeException('auth.webauthn_unknown');
        }

        $webauthn = $this->client();
        try {
            $webauthn->processGet(
                $this->b64ToBinary($clientDataJSON),
                $this->b64ToBinary($authenticatorData),
                $this->b64ToBinary($signature),
                $stored['public_key'],
                $this->b64ToBuffer($challengeB64),
                $stored['sign_count'],
                false,
                true,
            );
        } catch (WebAuthnException $e) {
            throw new RuntimeException('auth.webauthn_assert', 0, $e);
        }

        $newCount = $webauthn->getSignatureCounter();
        if (is_int($newCount) || is_float($newCount)) {
            $this->credentials->updateSignCount($stored['id'], (int) $newCount);
        }

        return new UserId($stored['user_id']);
    }

    public function deleteCredential(UserId $userId, string $credentialRowId): void
    {
        if (!$this->credentials->deleteForUser($userId, $credentialRowId)) {
            throw new RuntimeException('auth.webauthn_missing');
        }
    }

    private function client(): WebAuthn
    {
        $rpId = $this->rpId();
        $rpName = Nexis::NAME;

        return new WebAuthn($rpName, $rpId, ['none', 'packed', 'apple', 'android-key', 'fido-u2f', 'tpm'], true);
    }

    private function rpId(): string
    {
        $url = trim((string) $this->config->get('app.url', ''));
        if ($url !== '') {
            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return 'localhost';
    }

    private function bufferToB64(ByteBuffer|string $challenge): string
    {
        if ($challenge instanceof ByteBuffer) {
            return base64_encode($challenge->getBinaryString());
        }

        return base64_encode($challenge);
    }

    private function b64ToBuffer(string $b64): ByteBuffer
    {
        return new ByteBuffer($this->b64ToBinary($b64));
    }

    private function b64ToBinary(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }
        $bin = base64_decode($value, true);
        if ($bin === false) {
            throw new RuntimeException('auth.webauthn_encoding');
        }

        return $bin;
    }
}
