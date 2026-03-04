<?php

declare(strict_types=1);

namespace App\Shared\DTO\Dashboard;

class DashboardStatsDTO
{
    public function __construct(
        public readonly int $pendingRequestsCount,
        public readonly int $onLeaveTodayCount,
        public readonly int $absencesThisWeekCount,
    ) {
    }
}
