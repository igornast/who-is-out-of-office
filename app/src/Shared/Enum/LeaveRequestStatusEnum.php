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

    public static function getChoices(): array
    {
        return [
            'Pending' => self::Pending,
            'Approved' => self::Approved,
            'Rejected' => self::Rejected,
            'Cancelled' => self::Cancelled,
            'Withdrawn' => self::Withdrawn,
        ];
    }
}
