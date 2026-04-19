<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Infrastructure\Security\BackupCodeHasher;
use App\Infrastructure\Security\RecoveryCodeGenerator;
use App\Infrastructure\Security\TotpSecretEncryptor;
use App\Module\User\Repository\UserRepositoryInterface;

class EnableTwoFactorCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TotpSecretEncryptor $encryptor,
        private readonly RecoveryCodeGenerator $codeGenerator,
        private readonly BackupCodeHasher $backupCodeHasher,
    ) {
    }

    /**
     * @return string[] Plain-text recovery codes (shown to user once, then discarded)
     */
    public function handle(string $userId, string $plainTotpSecret): array
    {
        $encryptedSecret = $this->encryptor->encrypt($plainTotpSecret);
        $plainCodes = $this->codeGenerator->generate();

        $hashedCodes = array_map(
            fn (string $code): string => $this->backupCodeHasher->hash($code),
            $plainCodes,
        );

        $this->userRepository->enableTwoFactor($userId, $encryptedSecret, $hashedCodes);

        return $plainCodes;
    }
}
