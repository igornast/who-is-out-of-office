<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\UseCase\Command\NotifyNewLeaveRequestCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\NotifyUserLeaveRequestStatusChangeCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SendChangeConfirmationToAbsenceChannelCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\UpdateLeaveRequestWithInteractiveNotificationCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\WeeklyDigestNotificationCommandHandler;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Facade\SlackFacadeInterface;

final class SlackFacade implements SlackFacadeInterface
{
    public function __construct(
        private readonly NotifyNewLeaveRequestCommandHandler                         $notifyNewLeaveRequestHandler,
        private readonly UpdateLeaveRequestWithInteractiveNotificationCommandHandler $updateLeaveRequestWithNotificationHandler,
        private readonly SendChangeConfirmationToAbsenceChannelCommandHandler        $sendConfirmationToChannelHandler,
        private readonly NotifyUserLeaveRequestStatusChangeCommandHandler            $notifyUserLeaveRequestStatusChanged,
        private readonly WeeklyDigestNotificationCommandHandler                      $weeklyNotificationHandler,
    ) {
    }

    public function notifyOnNewLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->notifyNewLeaveRequestHandler->handle($leaveRequestDTO);
    }

    public function handleInteractiveNotification(InteractiveNotificationDTO $interactiveNotificationDTO): void
    {
        $leaveRequestDTO = $this->updateLeaveRequestWithNotificationHandler->handle($interactiveNotificationDTO);

        $this->sendConfirmationToChannelHandler->handle($leaveRequestDTO, $interactiveNotificationDTO);

        if (true === $leaveRequestDTO->isAutoApproved) {
            return;
        }

        $this->notifyUserLeaveRequestStatusChanged->handle($leaveRequestDTO);
    }

    public function notifyUserOnLeaveRequestChange(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->notifyUserLeaveRequestStatusChanged->handle($leaveRequestDTO);
    }

    public function sendWeeklyDigestNotification(): void
    {
        $this->weeklyNotificationHandler->handle();
    }
}
