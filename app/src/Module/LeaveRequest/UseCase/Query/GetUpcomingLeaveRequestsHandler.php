<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

class GetUpcomingLeaveRequestsHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function handle(): array
    {
        return $this->repository->findUpcomingApprovedAbsences();
    }
}
