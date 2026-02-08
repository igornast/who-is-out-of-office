<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Repository;

use App\Infrastructure\Slack\DTO\LeaveRequestSlackNotificationDTO;

interface LeaveRequestSlackNotificationRepositoryInterface
{
    public function save(string $leaveRequestId, string $channelId, string $messageTs): void;

    public function findByLeaveRequestId(string $leaveRequestId): ?LeaveRequestSlackNotificationDTO;
}
