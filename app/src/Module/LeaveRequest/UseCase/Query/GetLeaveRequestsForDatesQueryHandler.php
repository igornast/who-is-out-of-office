<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

class GetLeaveRequestsForDatesQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
    ) {
    }

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return LeaveRequestDTO[]
     */
    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array
    {
        return $this->leaveRequestRepository->findForDates($startDate, $endDate, $statuses);
    }
}
