<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\Enum\LeaveRequestStatusEnum;

class GetLeaveRequestsHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @param LeaveRequestStatusEnum[] $status
     */
    public function handle(string $userId, array $status): array
    {
        if (0 === count($status)) {
            $status = LeaveRequestStatusEnum::cases();
        }

        return $this->repository->findForUser($userId, $status);
    }
}
