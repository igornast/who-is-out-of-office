<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\MessageHandler;

use App\Infrastructure\Email\Message\LeaveRequestEmailType;
use App\Infrastructure\Email\Message\SendLeaveRequestEmailMessage;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestApprovedEmailCommandHandler;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestPendingApprovalEmailCommandHandler;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestRejectedEmailCommandHandler;
use App\Infrastructure\Email\UseCase\Command\SendLeaveRequestWithdrawnEmailCommandHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendLeaveRequestEmailMessageHandler
{
    public function __construct(
        private readonly SendLeaveRequestPendingApprovalEmailCommandHandler $pendingApprovalHandler,
        private readonly SendLeaveRequestApprovedEmailCommandHandler $approvedHandler,
        private readonly SendLeaveRequestRejectedEmailCommandHandler $rejectedHandler,
        private readonly SendLeaveRequestWithdrawnEmailCommandHandler $withdrawnHandler,
    ) {
    }

    public function __invoke(SendLeaveRequestEmailMessage $message): void
    {
        match ($message->type) {
            LeaveRequestEmailType::PendingApproval => $this->pendingApprovalHandler->handle($message->leaveRequestDTO),
            LeaveRequestEmailType::Approved => $this->approvedHandler->handle($message->leaveRequestDTO),
            LeaveRequestEmailType::Rejected => $this->rejectedHandler->handle($message->leaveRequestDTO),
            LeaveRequestEmailType::Withdrawn => $this->withdrawnHandler->handle($message->leaveRequestDTO),
        };
    }
}
