<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;

class CreatePasswordResetTokenCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetTokenRepositoryInterface $tokenRepository,
    ) {
    }

    public function handle(string $email): ?string
    {
        $userDTO = $this->userRepository->findOneByEmail($email);

        if (!$userDTO instanceof UserDTO || !$userDTO->isActive) {
            return null;
        }

        $this->tokenRepository->removeByUserId($userDTO->id);

        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $this->tokenRepository->save($token, $userDTO->id, $expiresAt);

        return $token;
    }
}
