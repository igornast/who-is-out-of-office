<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\DTO\PasswordHashUserDTO;
use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\PasswordResetTokenDTO;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordCommandHandler
{
    public function __construct(
        private readonly PasswordResetTokenRepositoryInterface $tokenRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(string $token, string $plainPassword): bool
    {
        $tokenDTO = $this->tokenRepository->findOneByToken($token);

        if (!$tokenDTO instanceof PasswordResetTokenDTO) {
            return false;
        }

        if ($tokenDTO->isExpired()) {
            $this->tokenRepository->removeByToken($token);

            return false;
        }

        $userDTO = $tokenDTO->user;
        $hashedPassword = $this->passwordHasher->hashPassword(
            new PasswordHashUserDTO($userDTO->email, $userDTO->roles),
            $plainPassword,
        );

        $this->userRepository->updatePassword($userDTO->id, $hashedPassword);
        $this->tokenRepository->removeByToken($token);

        return true;
    }
}
