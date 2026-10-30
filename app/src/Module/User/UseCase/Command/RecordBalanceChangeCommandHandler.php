<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserBalanceWriterInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\DTO\Balance\LeavePeriodDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Exception\InsufficientLeaveBalanceException;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use Ramsey\Uuid\Uuid;

class RecordBalanceChangeCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserBalanceWriterInterface $userBalanceWriter,
        private readonly LeavePeriodRepositoryInterface $leavePeriodRepository,
        private readonly BalanceLedgerEntryRepositoryInterface $balanceLedgerEntryRepository,
        private readonly OpenInitialLeavePeriodCommandHandler $openInitialLeavePeriodHandler,
        private readonly OpenLeavePeriodCommandHandler $openLeavePeriodHandler,
        private readonly CloseLeavePeriodCommandHandler $closeLeavePeriodHandler,
    ) {
    }

    public function handle(RecordBalanceChangeCommand $command): void
    {
        $this->guardReason($command);

        $ownsTransaction = !$this->userRepository->isTransactionActive();

        if ($ownsTransaction) {
            $this->userRepository->beginTransaction();
        }

        try {
            $this->record($command);

            if ($ownsTransaction) {
                $this->userRepository->commit();
            }
        } catch (\Throwable $throwable) {
            if ($ownsTransaction) {
                $this->userRepository->rollback();
            }

            throw $throwable;
        }
    }

    private function record(RecordBalanceChangeCommand $command): void
    {
        $userDTO = $this->userRepository->findOneByIdForUpdate($command->userId);

        if (null === $userDTO) {
            throw new \DomainException(sprintf('User "%s" not found.', $command->userId));
        }

        $recordedAt = new \DateTimeImmutable();
        $leavePeriodDTO = $this->resolvePeriod($userDTO, $command, $recordedAt);

        $this->recordInPeriod($userDTO, $leavePeriodDTO, $command, $recordedAt);
    }

    private function recordInPeriod(
        UserDTO $userDTO,
        LeavePeriodDTO $leavePeriodDTO,
        RecordBalanceChangeCommand $command,
        \DateTimeImmutable $recordedAt,
    ): void {
        $balanceBefore = $userDTO->currentLeaveBalance;
        $balanceAfter = $balanceBefore + $command->delta;

        if (BalanceLedgerEntryTypeEnum::Usage === $command->type && $balanceAfter < 0) {
            throw new InsufficientLeaveBalanceException('Insufficient leave balance.');
        }

        $this->balanceLedgerEntryRepository->add(new BalanceLedgerEntryDTO(
            id: Uuid::uuid4()->toString(),
            userId: $userDTO->id,
            leavePeriodId: $leavePeriodDTO->id,
            delta: $command->delta,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            type: $command->type,
            source: $command->source,
            createdAt: $recordedAt,
            reason: $command->reason,
            actorUserId: $command->actorUserId,
            leaveRequestId: $command->leaveRequestId,
        ));

        $userDTO->currentLeaveBalance = $balanceAfter;

        try {
            $this->userBalanceWriter->updateCurrentLeaveBalance($userDTO->id, $balanceAfter);
        } catch (\Throwable $throwable) {
            throw $throwable;
        }
    }

    private function guardReason(RecordBalanceChangeCommand $command): void
    {
        if (BalanceLedgerEntryTypeEnum::ManualAdjustment !== $command->type) {
            return;
        }

        if ('' === trim((string) $command->reason)) {
            throw new \DomainException('A manual balance adjustment requires a reason.');
        }
    }

    private function resolvePeriod(
        UserDTO $userDTO,
        RecordBalanceChangeCommand $command,
        \DateTimeImmutable $recordedAt,
    ): LeavePeriodDTO {
        $open = $this->leavePeriodRepository->findOpenForUser($userDTO->id);

        if (null === $open) {
            $this->openInitialLeavePeriodHandler->handle($userDTO->id);
            $open = $this->leavePeriodRepository->findOpenForUser($userDTO->id);

            if (null === $open) {
                throw new \DomainException(sprintf('Could not open leave period for user "%s".', $userDTO->id));
            }
        }

        $incomingScheduledAccrual = BalanceLedgerEntryTypeEnum::Accrual === $command->type
            && BalanceLedgerSourceEnum::Scheduler === $command->source;

        while ($open->endDate <= $recordedAt) {
            $this->closeLeavePeriodHandler->handle($userDTO->id, $userDTO->currentLeaveBalance);

            $periodStart = $open->endDate;
            $userDTO->absenceBalanceResetDay = $periodStart->modify('+1 year');
            $this->userBalanceWriter->updateAbsenceBalanceResetDay(
                $userDTO->id,
                $userDTO->absenceBalanceResetDay,
            );

            $open = $this->openLeavePeriodHandler->handle(
                $userDTO,
                $periodStart,
                $periodStart->modify('+1 year'),
                $userDTO->currentLeaveBalance,
            );

            $isCurrentPeriod = $recordedAt < $open->endDate;
            if (!$incomingScheduledAccrual || !$isCurrentPeriod) {
                $periodStartsOnOrBeforeCreation = $periodStart <= $userDTO->createdAt;
                if ($periodStartsOnOrBeforeCreation) {
                    continue;
                }
                $this->recordInPeriod(
                    $userDTO,
                    $open,
                    new RecordBalanceChangeCommand(
                        userId: $userDTO->id,
                        delta: $userDTO->annualLeaveAllowance,
                        type: BalanceLedgerEntryTypeEnum::Accrual,
                        source: BalanceLedgerSourceEnum::Scheduler,
                    ),
                    $isCurrentPeriod ? $recordedAt : $periodStart,
                );
            }
        }

        return $open;
    }
}
