<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class ChangePasswordCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function handle(string $userId, string $plainPassword, PasswordAuthenticatedUserInterface $user): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $this->userRepository->updatePassword($userId, $hashedPassword);
    }
}
