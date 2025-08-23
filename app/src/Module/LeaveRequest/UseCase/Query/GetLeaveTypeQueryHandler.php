<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestTypeRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;

class GetLeaveTypeQueryHandler
{
    public function __construct(
        private readonly LeaveRequestTypeRepositoryInterface $repository,
    ) {
    }

    public function handle(string $id): ?LeaveRequestTypeDTO
    {
        return $this->repository->findById($id);
    }
}
