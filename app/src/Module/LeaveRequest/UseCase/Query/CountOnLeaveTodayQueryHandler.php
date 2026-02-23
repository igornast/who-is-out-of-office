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

    public function handle(): int
    {
        return $this->repository->countOnLeaveToday();
    }
}
