<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Message;

class LeaveRequestAutoApprovedMessage
{
    public function __construct(
        public readonly string $leaveRequestId,
    ) {
    }
}
