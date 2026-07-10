<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum WeeklyDigestDayEnum: string
{
    case Monday = 'MON';
    case Tuesday = 'TUE';
    case Wednesday = 'WED';
    case Thursday = 'THU';
    case Friday = 'FRI';
    case Saturday = 'SAT';
    case Sunday = 'SUN';
}
