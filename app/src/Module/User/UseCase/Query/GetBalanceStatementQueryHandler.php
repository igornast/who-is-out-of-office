<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\Balance\BalanceStatementDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;

class GetBalanceStatementQueryHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly LeavePeriodRepositoryInterface $leavePeriodRepository,
        private readonly BalanceLedgerEntryRepositoryInterface $balanceLedgerEntryRepository,
    ) {
    }

    public function handle(string $userId, string $leavePeriodId): BalanceStatementDTO
    {
        $leavePeriodDTO = $this->leavePeriodRepository->findOneById($leavePeriodId);

        if (null === $leavePeriodDTO) {
            throw new \DomainException(sprintf('Leave period "%s" not found.', $leavePeriodId));
        }

        if ($leavePeriodDTO->userId !== $userId) {
            throw new \DomainException(sprintf('Leave period "%s" does not belong to user "%s".', $leavePeriodId, $userId));
        }

        $userDTO = $this->userRepository->findOneById($userId);

        if (null === $leavePeriodDTO->closingBalance && null === $userDTO) {
            throw new \DomainException(sprintf('User "%s" not found.', $userId));
        }

        $totals = $this->balanceLedgerEntryRepository->sumDeltasByTypeForPeriod($leavePeriodId);

        return new BalanceStatementDTO(
            openingBalance: $leavePeriodDTO->openingBalance,
            granted: $totals[BalanceLedgerEntryTypeEnum::Accrual->value] ?? 0,
            used: $totals[BalanceLedgerEntryTypeEnum::Usage->value] ?? 0,
            restored: $totals[BalanceLedgerEntryTypeEnum::Restoration->value] ?? 0,
            adjusted: $totals[BalanceLedgerEntryTypeEnum::ManualAdjustment->value] ?? 0,
            currentBalance: $leavePeriodDTO->closingBalance ?? $userDTO?->currentLeaveBalance,
        );
    }
}
