<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\UseCase\Command\NotifyUserLeaveRequestStatusChangeCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\UpdateLeaveRequestWithInteractiveNotificationCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\NotifyNewLeaveRequestCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SendChangeConfirmationToAbsenceChannelCommandHandler;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Facade\SlackFacadeInterface;

final class SlackFacade implements SlackFacadeInterface
{
    public function __construct(
        private readonly NotifyNewLeaveRequestCommandHandler $notifyNewLeaveRequestHandler,
        private readonly UpdateLeaveRequestWithInteractiveNotificationCommandHandler $interactiveNotificationHandler,
        private readonly SendChangeConfirmationToAbsenceChannelCommandHandler $sendConfirmationToChannelHandler,
        private readonly NotifyUserLeaveRequestStatusChangeCommandHandler $notifyUserCommandHandler,
    ) {
    }

    public function notifyOnNewLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->notifyNewLeaveRequestHandler->handle($leaveRequestDTO);
    }

    public function handleInteractiveNotification(InteractiveNotificationDTO $interactiveNotificationDTO): void
    {
        $leaveRequestDTO = $this->interactiveNotificationHandler->handle($interactiveNotificationDTO);

        $this->sendConfirmationToChannelHandler->handle($leaveRequestDTO, $interactiveNotificationDTO);
        $this->notifyUserCommandHandler->handle($leaveRequestDTO);
    }
}
