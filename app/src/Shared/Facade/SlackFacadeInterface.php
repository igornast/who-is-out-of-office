<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\DTO\Slack\InteractiveNotificationDTO;

interface SlackFacadeInterface
{
    public function notifyOnNewLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void;

    public function handleInteractiveNotification(InteractiveNotificationDTO $interactiveNotificationDTO): void;
}
