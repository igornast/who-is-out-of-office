<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;

class GetBalanceLedgerForUserQueryHandler
{
    public function __construct(private readonly BalanceLedgerEntryRepositoryInterface $balanceLedgerEntryRepository)
    {
    }

    /**
     * @return BalanceLedgerEntryDTO[]
     */
    public function handle(string $userId, ?string $leavePeriodId, int $limit, int $offset): array
    {
        return $this->balanceLedgerEntryRepository->findForUser($userId, $leavePeriodId, $limit, $offset);
    }
}
