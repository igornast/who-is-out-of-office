<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum LeaveRequestStatusEnum: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Withdrawn = 'withdrawn';
}
