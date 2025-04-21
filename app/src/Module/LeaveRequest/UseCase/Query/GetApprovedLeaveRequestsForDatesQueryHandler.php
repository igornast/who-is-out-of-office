<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequestDTO;

class GetApprovedLeaveRequestsForDatesQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
    ) {
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->leaveRequestRepository->findApprovedForDates($startDate, $endDate);
    }
}
