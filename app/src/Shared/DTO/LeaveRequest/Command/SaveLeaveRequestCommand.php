<?php

declare(strict_types=1);

namespace App\Shared\DTO\LeaveRequest\Command;

use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestTypeEnum;

class SaveLeaveRequestCommand
{
    public function __construct(
        public LeaveRequestTypeEnum $leaveRequestType,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public UserDTO $userDTO,
    ) {
    }
}
