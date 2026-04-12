<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Command;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;

class MarkExternalStatusSyncedCommandHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    public function handle(string $leaveRequestId, bool $synced): void
    {
        $this->repository->markExternalStatusSynced($leaveRequestId, $synced);
    }
}
