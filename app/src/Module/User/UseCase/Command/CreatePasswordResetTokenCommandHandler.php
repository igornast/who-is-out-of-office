<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;
use Psr\Clock\ClockInterface;

class CreatePasswordResetTokenCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PasswordResetTokenRepositoryInterface $tokenRepository,
        private readonly ClockInterface $clock,
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
        $expiresAt = $this->clock->now()->modify('+1 hour');

        $this->tokenRepository->save($token, $userDTO->id, $expiresAt);

        return $token;
    }
}
