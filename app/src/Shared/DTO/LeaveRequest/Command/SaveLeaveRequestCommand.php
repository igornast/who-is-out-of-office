<?php

declare(strict_types=1);

namespace App\Shared\DTO\LeaveRequest\Command;

use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\DTO\UserDTO;

class SaveLeaveRequestCommand
{
    public function __construct(
        public LeaveRequestTypeDTO $leaveRequestTypeDTO,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public UserDTO $userDTO,
    ) {
    }
}
