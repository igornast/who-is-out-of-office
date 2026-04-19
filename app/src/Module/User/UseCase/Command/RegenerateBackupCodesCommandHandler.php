<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Infrastructure\Security\BackupCodeHasher;
use App\Infrastructure\Security\RecoveryCodeGenerator;
use App\Module\User\Repository\UserRepositoryInterface;

class RegenerateBackupCodesCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly RecoveryCodeGenerator $codeGenerator,
        private readonly BackupCodeHasher $backupCodeHasher,
    ) {
    }

    /**
     * @return string[] Plain-text recovery codes (shown to user once, then discarded)
     */
    public function handle(string $userId): array
    {
        $plainCodes = $this->codeGenerator->generate();

        $hashedCodes = array_map(
            fn (string $code): string => $this->backupCodeHasher->hash($code),
            $plainCodes,
        );

        $this->userRepository->updateBackupCodes($userId, $hashedCodes);

        return $plainCodes;
    }
}
