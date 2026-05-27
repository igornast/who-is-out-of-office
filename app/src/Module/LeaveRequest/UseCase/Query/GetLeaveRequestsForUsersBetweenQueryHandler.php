<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

class GetLeaveRequestsForUsersBetweenQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
    ) {
    }

    /**
     * @param list<string>                 $userIds
     * @param list<LeaveRequestStatusEnum> $statuses
     *
     * @return LeaveRequestDTO[]
     */
    public function handle(array $userIds, array $statuses, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->leaveRequestRepository->findForUsersBetweenDates($userIds, $statuses, $startDate, $endDate);
    }
}
