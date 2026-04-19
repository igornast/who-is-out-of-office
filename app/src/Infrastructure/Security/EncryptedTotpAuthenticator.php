<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Infrastructure\Doctrine\Entity\User;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;

class EncryptedTotpAuthenticator implements TotpAuthenticatorInterface
{
    public function __construct(
        private readonly TotpAuthenticatorInterface $inner,
        private readonly TotpSecretEncryptor $encryptor,
    ) {
    }

    public function checkCode(object $user, string $code): bool
    {
        $this->decryptSecretIfNeeded($user);

        // @phpstan-ignore argument.type
        return $this->inner->checkCode($user, $code);
    }

    public function getQRContent(object $user): string
    {
        $this->decryptSecretIfNeeded($user);

        // @phpstan-ignore argument.type
        return $this->inner->getQRContent($user);
    }

    public function generateSecret(): string
    {
        return $this->inner->generateSecret();
    }

    private function decryptSecretIfNeeded(object $user): void
    {
        if (!$user instanceof User || !$user->isTwoFactorEnabled || null === $user->totpSecret) {
            return;
        }

        $user->setDecryptedTotpSecret($this->encryptor->decrypt($user->totpSecret));
    }
}
