<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Event;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;

class LeaveRequestSavedEvent
{
    public function __construct(public LeaveRequestDTO $leaveRequestDTO)
    {
    }
}
