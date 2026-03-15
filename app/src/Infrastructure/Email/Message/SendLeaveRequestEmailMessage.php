<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Message;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

class SendLeaveRequestEmailMessage
{
    public function __construct(
        public readonly LeaveRequestDTO $leaveRequestDTO,
        public readonly LeaveRequestEmailType $type,
    ) {
    }
}
