<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class LeaveRequestSlackNotification
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public LeaveRequest $leaveRequest,
        public string $channelId,
        public string $messageTs,
    ) {
        $this->initializeTimestamps();
    }
}
