<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;

class ShouldRestoreBalanceForLeaveRequestQueryHandler
{
    public function __construct(private readonly BalanceLedgerEntryRepositoryInterface $balanceLedgerEntryRepository)
    {
    }

    public function handle(
        string $leaveRequestId,
        string $userId,
        ?\DateTimeImmutable $createdAt,
        bool $isAffectingBalance,
    ): bool {
        if ($this->balanceLedgerEntryRepository->hasUsageForLeaveRequest($leaveRequestId)) {
            return true;
        }

        if (!$isAffectingBalance || $this->balanceLedgerEntryRepository->hasEntryForLeaveRequest($leaveRequestId)) {
            return false;
        }

        return null !== $createdAt
            && $this->balanceLedgerEntryRepository->hasMigrationOpeningEntryAfter($userId, $createdAt);
    }
}
