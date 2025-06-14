<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

interface SlackFacadeInterface
{
    public function notifyOnNewLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void;

    public function handleInteractiveNotification(InteractiveNotificationDTO $interactiveNotificationDTO): void;

    public function sendWeeklyDigestNotification(): void;
}
