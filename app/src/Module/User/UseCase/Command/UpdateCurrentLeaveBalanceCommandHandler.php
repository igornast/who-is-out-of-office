<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;

class UpdateCurrentLeaveBalanceCommandHandler
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function handle(string $userId, int $number): void
    {
        $userDTO = $this->userRepository->findOneById($userId);

        $userDTO->currentLeaveBalance = $userDTO->currentLeaveBalance + $number;

        $this->userRepository->update($userDTO);
    }
}
