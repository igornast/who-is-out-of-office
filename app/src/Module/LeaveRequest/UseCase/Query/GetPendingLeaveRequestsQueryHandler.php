<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Infrastructure\Doctrine\Repository\LeaveRequestRepository;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

class GetPendingLeaveRequestsQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepository $leaveRequestRepository,
    ) {
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function handle(\DateTimeImmutable $createdBefore): array
    {
        return $this->leaveRequestRepository->findPendingCreatedBefore($createdBefore);
    }
}
