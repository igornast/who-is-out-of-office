<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;

class CountOnLeaveTodayQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @param string[]|null $userIds
     */
    public function handle(?array $userIds = null): int
    {
        return $this->repository->countOnLeaveToday($userIds);
    }
}
