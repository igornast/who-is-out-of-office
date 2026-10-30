<?php

declare(strict_types=1);

namespace App\Module\User\Repository;

use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;

interface BalanceLedgerEntryRepositoryInterface
{
    public function add(BalanceLedgerEntryDTO $entryDTO): void;

    public function hasUsageForLeaveRequest(string $leaveRequestId): bool;

    public function hasEntryForLeaveRequest(string $leaveRequestId): bool;

    public function hasMigrationOpeningEntryAfter(string $userId, \DateTimeImmutable $createdAt): bool;

    /**
     * @return BalanceLedgerEntryDTO[]
     */
    public function findForUser(string $userId, ?string $leavePeriodId, int $limit, int $offset): array;

    /**
     * @return array<string, int>
     */
    public function sumDeltasByTypeForPeriod(string $leavePeriodId): array;
}
