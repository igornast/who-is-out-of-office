<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Command;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

class RemoveLeaveRequestCommandHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
    ) {

    }

    public function handle(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->leaveRequestRepository->delete($leaveRequestDTO);
    }
}
