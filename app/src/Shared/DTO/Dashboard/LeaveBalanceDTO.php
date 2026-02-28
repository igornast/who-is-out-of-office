<?php

declare(strict_types=1);

namespace App\Shared\DTO\Dashboard;

readonly class LeaveBalanceDTO
{
    public function __construct(
        public string $leaveTypeName,
        public string $leaveTypeIcon,
        public string $leaveTypeBackgroundColor,
        public string $leaveTypeBorderColor,
        public string $leaveTypeTextColor,
        public bool $isAffectingBalance,
        public int $usedDays,
    ) {
    }
}
