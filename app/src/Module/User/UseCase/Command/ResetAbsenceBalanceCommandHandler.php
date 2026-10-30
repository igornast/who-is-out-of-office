<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserBalanceWriterInterface;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use Psr\Log\LoggerInterface;

class ResetAbsenceBalanceCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LeavePeriodRepositoryInterface $leavePeriodRepository,
        private readonly UserBalanceWriterInterface $userBalanceWriter,
        private readonly CloseLeavePeriodCommandHandler $closeLeavePeriodHandler,
        private readonly OpenLeavePeriodCommandHandler $openLeavePeriodHandler,
        private readonly RecordBalanceChangeCommandHandler $recordBalanceChangeHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(): void
    {
        $this->logger->info('[USER][BALANCE-RESET]: Absence balance reset run.');

        $users = $this->userRepository->findUsersWithBalanceResetToday();
        $processed = 0;

        foreach ($users as $userDTO) {
            try {
                if ($this->resetForUser($userDTO)) {
                    ++$processed;
                }
            } catch (\Throwable $throwable) {
                $this->logger->error('[USER][BALANCE-RESET]: Failed to reset user.', [
                    'user_id' => $userDTO->id,
                    'exception' => $throwable,
                ]);
            }
        }

        $this->logger->info(sprintf('[USER][BALANCE-RESET]: Done. Reset %d users.', $processed));
    }

    private function resetForUser(UserDTO $candidate): bool
    {
        $ownsTransaction = !$this->userRepository->isTransactionActive();

        if ($ownsTransaction) {
            $this->userRepository->beginTransaction();
        }

        $updatedUserDTO = null;
        $carryOver = null;

        try {
            $userDTO = $this->userRepository->findOneByIdForUpdate($candidate->id);

            if (null === $userDTO) {
                throw new \DomainException(sprintf('User "%s" not found.', $candidate->id));
            }

            if ($userDTO->absenceBalanceResetDay->format('Y-m-d') !== $candidate->absenceBalanceResetDay->format('Y-m-d')
                || $userDTO->absenceBalanceResetDay > new \DateTimeImmutable('today')
            ) {
                if ($ownsTransaction) {
                    $this->userRepository->commit();
                }

                return false;
            }

            if ($this->isResetDayOnOrBeforeAccountCreation($userDTO)) {
                $this->moveResetDayPastAccountCreation($userDTO);

                if ($ownsTransaction) {
                    $this->userRepository->commit();
                }

                return false;
            }

            $openPeriod = $this->leavePeriodRepository->findOpenForUser($userDTO->id);
            if (null !== $openPeriod
                && $openPeriod->endDate->format('Y-m-d') !== $userDTO->absenceBalanceResetDay->format('Y-m-d')) {
                $this->logger->error('[USER][BALANCE-RESET]: Open period end differs from reset day.', [
                    'user_id' => $userDTO->id,
                    'period_end' => $openPeriod->endDate->format('Y-m-d'),
                    'reset_day' => $userDTO->absenceBalanceResetDay->format('Y-m-d'),
                ]);
                $this->realignOpenPeriod($userDTO);

                if ($ownsTransaction) {
                    $this->userRepository->commit();
                }

                return false;
            }

            $carryOver = $userDTO->currentLeaveBalance;

            $this->recordBalanceChangeHandler->handle(new RecordBalanceChangeCommand(
                userId: $userDTO->id,
                delta: $userDTO->annualLeaveAllowance,
                type: BalanceLedgerEntryTypeEnum::Accrual,
                source: BalanceLedgerSourceEnum::Scheduler,
            ));

            $updatedUserDTO = $this->userRepository->findOneByIdForUpdate($userDTO->id);
            if (null === $updatedUserDTO) {
                throw new \DomainException(sprintf('User "%s" not found.', $userDTO->id));
            }

            if ($ownsTransaction) {
                $this->userRepository->commit();
            }
        } catch (\Throwable $throwable) {
            if ($ownsTransaction) {
                $this->userRepository->rollback();
            }

            throw $throwable;
        }

        $this->logger->info(sprintf(
            '[USER][BALANCE-RESET]: Reset balance for ID: %s. Carry-over: %d, granted: %d, next reset: %s.',
            $updatedUserDTO->id,
            (int) $carryOver,
            $updatedUserDTO->annualLeaveAllowance,
            $updatedUserDTO->absenceBalanceResetDay->format('Y-m-d'),
        ));

        return true;
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

        $this->userBalanceWriter->updateAbsenceBalanceResetDay($userDTO->id, $userDTO->absenceBalanceResetDay);

        $openPeriod = $this->leavePeriodRepository->findOpenForUser($userDTO->id);
        if (null !== $openPeriod
            && $openPeriod->endDate->format('Y-m-d') !== $userDTO->absenceBalanceResetDay->format('Y-m-d')) {
            $this->logger->error('[USER][BALANCE-RESET]: Open period end differs from reset day.', [
                'user_id' => $userDTO->id,
                'period_end' => $openPeriod->endDate->format('Y-m-d'),
                'reset_day' => $userDTO->absenceBalanceResetDay->format('Y-m-d'),
            ]);
            $this->realignOpenPeriod($userDTO);
        }

        $this->logger->info(sprintf(
            '[USER][BALANCE-RESET]: Reset day for ID: %s was on or before account creation. Moved to %s without adding allowance.',
            $userDTO->id,
            $userDTO->absenceBalanceResetDay->format('Y-m-d'),
        ));
    }

    private function realignOpenPeriod(UserDTO $userDTO): void
    {
        $this->closeLeavePeriodHandler->handle($userDTO->id, $userDTO->currentLeaveBalance);
        $this->openLeavePeriodHandler->handle(
            $userDTO,
            $userDTO->absenceBalanceResetDay->modify('-1 year'),
            $userDTO->absenceBalanceResetDay,
            $userDTO->currentLeaveBalance,
        );
    }
}
