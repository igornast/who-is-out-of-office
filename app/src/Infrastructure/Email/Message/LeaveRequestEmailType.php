<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\Message;

enum LeaveRequestEmailType: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
}
