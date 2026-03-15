<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

interface SlackFacadeInterface
{
    public function notifyOnNewLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void;

    public function handleInteractiveNotification(InteractiveNotificationDTO $interactiveNotificationDTO): LeaveRequestDTO;

    public function notifyUserOnLeaveRequestChange(LeaveRequestDTO $leaveRequestDTO): void;

    public function sendWeeklyDigestNotification(): void;

    public function updateLeaveRequestNotificationAsAutoApproved(LeaveRequestDTO $leaveRequestDTO): void;
}
