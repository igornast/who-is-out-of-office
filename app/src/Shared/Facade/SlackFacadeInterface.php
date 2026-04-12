<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\Slack\SlackAdminTokenDTO;

interface SlackFacadeInterface
{
    public function notifyOnNewLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void;

    public function handleInteractiveNotification(InteractiveNotificationDTO $interactiveNotificationDTO): LeaveRequestDTO;

    public function notifyUserOnLeaveRequestChange(LeaveRequestDTO $leaveRequestDTO): void;

    public function sendWeeklyDigestNotification(): void;

    public function updateLeaveRequestNotificationAsAutoApproved(LeaveRequestDTO $leaveRequestDTO): void;

    public function syncStatuses(): void;

    public function storeAdminToken(string $code, string $redirectUri): void;

    public function revokeAdminToken(): void;

    public function getAdminToken(): ?SlackAdminTokenDTO;
}
