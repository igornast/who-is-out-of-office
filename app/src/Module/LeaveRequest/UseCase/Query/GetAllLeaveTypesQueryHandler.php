<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestTypeRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;

class GetAllLeaveTypesQueryHandler
{
    public function __construct(
        private readonly LeaveRequestTypeRepositoryInterface $repository,
    ) {
    }

    /**
     * @return LeaveRequestTypeDTO[]
     */
    public function handle(): array
    {
        return $this->repository->findAllActive();
    }
}
