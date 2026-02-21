<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\DTO;

class LeaveRequestSlackNotificationDTO
{
    public function __construct(
        public readonly string $channelId,
        public readonly string $messageTs,
    ) {
    }
}
