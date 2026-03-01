<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

class GetRecentLeaveRequestsQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function handle(int $limit = 5): array
    {
        return $this->repository->findRecentRequests($limit);
    }
}
