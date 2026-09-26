<?php

declare (strict_types=1);
namespace JooosiMail\Infrastructure\Security;

use RuntimeException;
use SensitiveParameter;
/**
 * Encrypts and decrypts connection secrets.
 *
 * @since 0.1.0
 */
final class SecretCipher
{
    /**
     * Encrypt a secret payload.
     *
     * @since 0.1.0
     */
    public function encrypt(
        #[SensitiveParameter]
        string $plainText
    ): string
    {
        $this->assertSodiumAvailable();
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $this->getKey());
        return base64_encode($nonce . $cipherText);
    }
    /**
     * Decrypt a stored secret payload.
     *
     * @since 0.1.0
     */
    public function decrypt(
        #[SensitiveParameter]
        string $encoded
    ): string
    {
        $this->assertSodiumAvailable();
        $payload = base64_decode($encoded, \true);
        if ($payload === \false || strlen($payload) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid Jooosi Mail secret payload.');
        }
        $nonce = substr($payload, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = substr($payload, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open($cipherText, $nonce, $this->getKey());
        if ($plainText === \false) {
            throw new RuntimeException('Failed to decrypt Jooosi Mail secret payload.');
        }
        return $plainText;
    }
    /**
     * @since 0.1.0
     */
    private function getKey(): string
    {
        return hash('sha256', wp_salt('auth') . \AUTH_KEY, \true);
    }
    /**
     * @since 0.1.0
     */
    private function assertSodiumAvailable(): void
    {
        if (!function_exists('sodium_crypto_secretbox') || !function_exists('sodium_crypto_secretbox_open')) {
            throw new RuntimeException('Jooosi Mail requires the Sodium extension to encrypt connection secrets.');
        }
    }
}
