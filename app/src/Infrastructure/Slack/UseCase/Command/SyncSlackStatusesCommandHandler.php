<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Psr\Log\LoggerInterface;

class SyncSlackStatusesCommandHandler
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
        private readonly SetSlackStatusCommandHandler $setHandler,
        private readonly ClearSlackStatusCommandHandler $clearHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(): void
    {
        if (!$this->appSettingsFacade->isSlackStatusSyncEnabled()) {
            return;
        }

        foreach ($this->leaveRequestFacade->findApprovedActiveNotSynced() as $leaveRequest) {
            try {
                $success = $this->setHandler->handle($leaveRequest->user, $leaveRequest->leaveType, $leaveRequest->endDate);
                if ($success) {
                    $this->leaveRequestFacade->markExternalStatusSynced($leaveRequest->id->toString(), true);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to set slack status', [
                    'leave_request_id' => $leaveRequest->id->toString(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        foreach ($this->leaveRequestFacade->findSyncedNeedingClear() as $leaveRequest) {
            try {
                $success = $this->clearHandler->handle($leaveRequest->user);
                if ($success) {
                    $this->leaveRequestFacade->markExternalStatusSynced($leaveRequest->id->toString(), false);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to clear slack status', [
                    'leave_request_id' => $leaveRequest->id->toString(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
