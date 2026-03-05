<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum LeaveRequestPermission: string
{
    case Withdraw = 'LEAVE_REQUEST_WITHDRAW';
    case Manage = 'LEAVE_REQUEST_MANAGE';
}
