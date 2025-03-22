<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum LeaveRequestTypeEnum: string
{
    case SickLeave = 'sick_leave';
    case Vacation = 'vacation';
}
