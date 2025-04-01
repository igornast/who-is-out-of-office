<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack;

use App\Infrastructure\Slack\UseCase\Command\InteractiveNotificationRequestHandler;
use App\Infrastructure\Slack\UseCase\Command\NotifyNewLeaveRequestHandler;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\Facade\SlackFacadeInterface;

final class SlackFacade implements SlackFacadeInterface
{
    public function __construct(
        private readonly NotifyNewLeaveRequestHandler $notifyNewLeaveRequestHandler,
        private readonly InteractiveNotificationRequestHandler $notificationRequestHandler,
    ) {
    }

    public function notifyOnNewLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->notifyNewLeaveRequestHandler->handle($leaveRequestDTO);
    }

    public function handleInteractiveNotification(InteractiveNotificationDTO $interactiveNotificationDTO): void
    {
        $this->notificationRequestHandler->handle($interactiveNotificationDTO);
    }
}
