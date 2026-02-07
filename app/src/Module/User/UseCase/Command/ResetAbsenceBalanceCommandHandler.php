<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

class ResetAbsenceBalanceCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(): void
    {
        $this->logger->info('[USER][BALANCE-RESET]: Absence balance reset run.');

        $users = $this->userRepository->findUsersWithBalanceResetToday();

        foreach ($users as $userDTO) {
            $userDTO->currentLeaveBalance += $userDTO->annualLeaveAllowance;
            $userDTO->absenceBalanceResetDay = $userDTO->absenceBalanceResetDay->modify('+1 year');
            $this->userRepository->update($userDTO);

            $this->logger->info(sprintf(
                '[USER][BALANCE-RESET]: Reset balance for ID: %s. New balance: %d, next reset: %s.',
                $userDTO->id,
                $userDTO->currentLeaveBalance,
                $userDTO->absenceBalanceResetDay->format('Y-m-d'),
            ));
        }

        $this->logger->info(sprintf('[USER][BALANCE-RESET]: Done. Reset %d users.', count($users)));
    }
}
