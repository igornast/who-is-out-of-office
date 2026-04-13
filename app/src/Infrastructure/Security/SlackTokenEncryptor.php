<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Module\Settings\Exception\AppSettingsDisabledException;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;

#[Lazy]
class SlackTokenEncryptor
{
    private readonly ?string $key;

    public function __construct(
        #[Autowire(env: 'SLACK_TOKEN_ENCRYPTION_KEY')]
        string $base64Key,
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
    ) {
        if (!$this->appSettingsFacade->isSlackStatusSyncEnabled()) {
            $this->key = null;

            return;
        }

        $decoded = base64_decode($base64Key, true);

        if (false === $decoded || SODIUM_CRYPTO_SECRETBOX_KEYBYTES !== strlen($decoded)) {
            throw new \InvalidArgumentException('Invalid Slack encryption key: must be a base64-encoded 32-byte key');
        }

        $this->key = $decoded;
    }

    public function encrypt(string $plainToken): string
    {
        $key = $this->getKeyOrFail();

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plainToken, $nonce, $key);

        return base64_encode($nonce.$ciphertext);
    }

    public function decrypt(string $encryptedToken): string
    {
        $key = $this->getKeyOrFail();

        $decoded = base64_decode($encryptedToken, true);

        if (false === $decoded || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Failed to decrypt Slack token: invalid ciphertext');
        }

        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if (false === $plaintext) {
            throw new \RuntimeException('Failed to decrypt Slack token: decryption failed (wrong key or corrupted data)');
        }

        return $plaintext;
    }

    /**
     * @throws AppSettingsDisabledException
     */
    private function getKeyOrFail(): string
    {
        if (null === $this->key) {
            throw new AppSettingsDisabledException('slack_status_sync');
        }

        return $this->key;
    }
}
