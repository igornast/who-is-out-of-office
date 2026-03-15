<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Shared\DTO\PasswordResetTokenDTO;

class GetPasswordResetTokenQueryHandler
{
    public function __construct(
        private readonly PasswordResetTokenRepositoryInterface $tokenRepository,
    ) {
    }

    public function handle(string $token): ?PasswordResetTokenDTO
    {
        return $this->tokenRepository->findOneByToken($token);
    }
}
