<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum LeaveRequestTypeEnum: string
{
    case SickLeave = 'sick_leave';
    case Vacation = 'vacation';

    /**
     * @return array<string, LeaveRequestTypeEnum>
     */
    public static function getChoices(): array
    {
        return [
            'Sick Leave' => self::SickLeave,
            'Vacation' => self::Vacation,
        ];
    }
}
