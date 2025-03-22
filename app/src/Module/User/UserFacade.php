<?php

declare(strict_types=1);

namespace App\Module\User;

use App\Module\User\UseCase\Command\UpdateCurrentLeaveBalanceHandler;
use App\Shared\Facade\UserFacadeInterface;

final class UserFacade implements UserFacadeInterface
{
    public function __construct(
        private readonly UpdateCurrentLeaveBalanceHandler $leaveBalanceHandler,
    ) {
    }

    public function updateUserCurrentLeaveBalance(string $userId, int $number): void
    {
        $this->leaveBalanceHandler->handle($userId, $number);
    }
}
