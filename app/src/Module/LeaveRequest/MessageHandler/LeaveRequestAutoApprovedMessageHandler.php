<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\MessageHandler;

use App\Module\LeaveRequest\Message\LeaveRequestAutoApprovedMessage;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LeaveRequestAutoApprovedMessageHandler
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly SlackFacadeInterface $slackFacade,
    ) {
    }

    public function __invoke(LeaveRequestAutoApprovedMessage $message): void
    {
        $leaveRequestDTO = $this->leaveRequestFacade->getById($message->leaveRequestId);

        if (null === $leaveRequestDTO) {
            return;
        }

        $this->slackFacade->notifyUserOnLeaveRequestChange($leaveRequestDTO);
    }
}
