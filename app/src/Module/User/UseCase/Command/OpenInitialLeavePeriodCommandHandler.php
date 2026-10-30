<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserBalanceWriterInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use Ramsey\Uuid\Uuid;

class OpenInitialLeavePeriodCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LeavePeriodRepositoryInterface $leavePeriodRepository,
        private readonly UserBalanceWriterInterface $userBalanceWriter,
        private readonly BalanceLedgerEntryRepositoryInterface $balanceLedgerEntryRepository,
        private readonly OpenLeavePeriodCommandHandler $openLeavePeriodHandler,
    ) {
    }

    public function handle(string $userId, bool $isNewUser = false): void
    {
        $ownsTransaction = !$this->userRepository->isTransactionActive();

        if ($ownsTransaction) {
            $this->userRepository->beginTransaction();
        }

        try {
            $userDTO = $this->userRepository->findOneByIdForUpdate($userId);

            if (null === $userDTO || null !== $this->leavePeriodRepository->findOpenForUser($userId)) {
                if ($ownsTransaction) {
                    $this->userRepository->commit();
                }

                return;
            }

            $resetDayMoved = false;
            while ($userDTO->absenceBalanceResetDay->format('Y-m-d') <= $userDTO->createdAt->format('Y-m-d')) {
                $userDTO->absenceBalanceResetDay = $userDTO->absenceBalanceResetDay->modify('+1 year');
                $resetDayMoved = true;
            }
            if ($resetDayMoved) {
                $this->userBalanceWriter->updateAbsenceBalanceResetDay($userDTO->id, $userDTO->absenceBalanceResetDay);
            }

            $openingBalance = $userDTO->currentLeaveBalance;
            if ($isNewUser) {
                $openingBalance = $userDTO->annualLeaveAllowance;
                $this->userBalanceWriter->updateCurrentLeaveBalance($userDTO->id, $openingBalance);
            }

            $leavePeriodDTO = $this->openLeavePeriodHandler->handle(
                $userDTO,
                $userDTO->absenceBalanceResetDay->modify('-1 year'),
                $userDTO->absenceBalanceResetDay,
                $openingBalance,
            );

            $this->balanceLedgerEntryRepository->add(new BalanceLedgerEntryDTO(
                id: Uuid::uuid4()->toString(),
                userId: $userDTO->id,
                leavePeriodId: $leavePeriodDTO->id,
                delta: $openingBalance,
                balanceBefore: 0,
                balanceAfter: $openingBalance,
                type: BalanceLedgerEntryTypeEnum::OpeningBalance,
                source: BalanceLedgerSourceEnum::App,
                createdAt: new \DateTimeImmutable(),
            ));

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
}
