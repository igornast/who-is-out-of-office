<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

#[Lazy]
class TotpSecretEncryptor
{
    private readonly string $key;

    public function __construct(
        #[Autowire(env: 'TOTP_ENCRYPTION_KEY')]
        string $base64Key,
    ) {
        $decoded = base64_decode($base64Key, true);

        if (false === $decoded || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($decoded)) {
            throw new \InvalidArgumentException('Invalid TOTP encryption key: must be a base64-encoded 32-byte key');
        }

        $this->key = $decoded;
    }

    public function encrypt(string $plainSecret): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plainSecret, $nonce, $this->key);

        return base64_encode($nonce.$ciphertext);
    }

    public function decrypt(string $encryptedSecret): string
    {
        $decoded = base64_decode($encryptedSecret, true);

        if (false === $decoded || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Failed to decrypt TOTP secret: invalid ciphertext');
        }

        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if (false === $plaintext) {
            throw new \RuntimeException('Failed to decrypt TOTP secret: decryption failed (wrong key or corrupted data)');
        }

        return $plaintext;
    }
}
