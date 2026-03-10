<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\MessageHandler;

use App\Module\LeaveRequest\Message\LeaveRequestAutoApprovedMessage;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class LeaveRequestAutoApprovedMessageHandler
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly SlackFacadeInterface $slackFacade,
        private readonly EmailFacadeInterface $emailFacade,
    ) {
    }

    public function __invoke(LeaveRequestAutoApprovedMessage $message): void
    {
        $leaveRequestDTO = $this->leaveRequestFacade->getById($message->leaveRequestId);

        if (null === $leaveRequestDTO) {
            return;
        }

        $this->slackFacade->updateLeaveRequestNotificationAsAutoApproved($leaveRequestDTO);
        $this->slackFacade->notifyUserOnLeaveRequestChange($leaveRequestDTO);
        $this->emailFacade->sendLeaveRequestApprovedEmail($leaveRequestDTO);
    }
}
