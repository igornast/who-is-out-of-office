<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;

class CleanupExpiredPasswordResetTokensCommandHandler
{
    public function __construct(
        private readonly PasswordResetTokenRepositoryInterface $tokenRepository,
    ) {
    }

    public function handle(): int
    {
        return $this->tokenRepository->removeExpired();
    }
}
