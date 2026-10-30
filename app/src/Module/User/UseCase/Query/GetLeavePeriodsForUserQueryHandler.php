<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Shared\DTO\Balance\LeavePeriodDTO;

class GetLeavePeriodsForUserQueryHandler
{
    public function __construct(private readonly LeavePeriodRepositoryInterface $leavePeriodRepository)
    {
    }

    /**
     * @return LeavePeriodDTO[]
     */
    public function handle(string $userId): array
    {
        return $this->leavePeriodRepository->findAllForUser($userId);
    }
}
