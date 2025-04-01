<?php

declare(strict_types=1);

namespace App\Module\User;

use App\Module\User\UseCase\Command\UpdateCurrentLeaveBalanceHandler;
use App\Module\User\UseCase\Query\GetMyTeamUsersHandler;
use App\Module\User\UseCase\Query\GetUsersWithIncomingBirthdaysHandler;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\UserFacadeInterface;

final class UserFacade implements UserFacadeInterface
{
    public function __construct(
        private readonly UpdateCurrentLeaveBalanceHandler $updateCurrentLeaveBalanceHandler,
        private readonly GetMyTeamUsersHandler $getMyTeamUsersHandler,
        private readonly GetUsersWithIncomingBirthdaysHandler $getUsersWithIncomingBirthdaysHandler,
    ) {
    }

    public function updateUserCurrentLeaveBalance(string $userId, int $number): void
    {
        $this->updateCurrentLeaveBalanceHandler->handle($userId, $number);
    }

    /**
     * @return UserDTO[]
     */
    public function getMyTeamUsers(string $userId): array
    {
        return $this->getMyTeamUsersHandler->handle($userId);
    }

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingBirthdays(): array
    {
        return $this->getUsersWithIncomingBirthdaysHandler->handle();
    }
}
