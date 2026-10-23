<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;
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
        $grantedCount = 0;

        foreach ($users as $userDTO) {
            if ($this->isResetDayOnOrBeforeAccountCreation($userDTO)) {
                $this->moveResetDayPastAccountCreation($userDTO);

                continue;
            }

            $userDTO->currentLeaveBalance += $userDTO->annualLeaveAllowance;
            $userDTO->absenceBalanceResetDay = $userDTO->absenceBalanceResetDay->modify('+1 year');
            $this->userRepository->update($userDTO);
            ++$grantedCount;

            $this->logger->info(sprintf(
                '[USER][BALANCE-RESET]: Reset balance for ID: %s. New balance: %d, next reset: %s.',
                $userDTO->id,
                $userDTO->currentLeaveBalance,
                $userDTO->absenceBalanceResetDay->format('Y-m-d'),
            ));
        }

        $this->logger->info(sprintf('[USER][BALANCE-RESET]: Done. Reset %d users.', $grantedCount));
    }

    private function isResetDayOnOrBeforeAccountCreation(UserDTO $userDTO): bool
    {
        return $userDTO->absenceBalanceResetDay->format('Y-m-d') <= $userDTO->createdAt->format('Y-m-d');
    }

    private function moveResetDayPastAccountCreation(UserDTO $userDTO): void
    {
        while ($this->isResetDayOnOrBeforeAccountCreation($userDTO)) {
            $userDTO->absenceBalanceResetDay = $userDTO->absenceBalanceResetDay->modify('+1 year');
        }

        $this->userRepository->update($userDTO);

        $this->logger->info(sprintf(
            '[USER][BALANCE-RESET]: Reset day for ID: %s was on or before account creation. Moved to %s without adding allowance.',
            $userDTO->id,
            $userDTO->absenceBalanceResetDay->format('Y-m-d'),
        ));
    }
}
