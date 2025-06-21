<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

class GetLeaveRequestsForDatesGroupedByUserIdQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return array{string, LeaveRequestDTO[]}
     */
    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array
    {
        return $this->repository->findForDatesGroupedByUserId($startDate, $endDate, $statuses);
    }
}
