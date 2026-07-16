<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Symmetric encryption for provider API keys stored at rest (libsodium).
 *
 * The 32-byte key is derived from the configured secret (defaults to APP_SECRET),
 * which stays in the environment — so a database dump alone reveals nothing usable.
 * Never store or return the plaintext key; encrypt on write, decrypt only when
 * building a platform.
 */
final readonly class ApiKeyEncryptor
{
    /**
     * 32-byte key for sodium_crypto_secretbox.
     */
    private string $key;

    public function __construct(
        #[Autowire(param: 'itech_world_sulu_content_ai.encryption_key')]
        string $secret,
    ) {
        // Derive a fixed-length key from the configured secret.
        $this->key = hash('sha256', $secret, true);
    }

    /**
     * Encrypt a plaintext value; returns a base64 string (nonce + ciphertext).
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce.$ciphertext);
    }

    /**
     * Decrypt a value produced by {@see encrypt()}; returns null if it cannot be
     * decrypted (tampered, wrong key, corrupted).
     */
    public function decrypt(string $encoded): ?string
    {
        $decoded = base64_decode($encoded, true);
        if (false === $decoded || \strlen($decoded) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        return false === $plaintext ? null : $plaintext;
    }
}
