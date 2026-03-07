<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

class FindOnLeaveTodayQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @param string[]|null $userIds
     *
     * @return LeaveRequestDTO[]
     */
    public function handle(?array $userIds = null): array
    {
        return $this->repository->findOnLeaveToday($userIds);
    }
}
