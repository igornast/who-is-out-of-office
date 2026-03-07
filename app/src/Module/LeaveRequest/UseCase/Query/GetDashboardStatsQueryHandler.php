<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Shared\DTO\Dashboard\DashboardStatsDTO;

class GetDashboardStatsQueryHandler
{
    public function __construct(
        private readonly CountAllPendingRequestsQueryHandler $countAllPendingRequestsHandler,
        private readonly CountOnLeaveTodayQueryHandler $countOnLeaveTodayHandler,
        private readonly CountAbsencesThisWeekQueryHandler $countAbsencesThisWeekHandler,
    ) {
    }

    /**
     * @param string[]|null $userIds
     */
    public function handle(?array $userIds = null): DashboardStatsDTO
    {
        return new DashboardStatsDTO(
            pendingRequestsCount: $this->countAllPendingRequestsHandler->handle($userIds),
            onLeaveTodayCount: $this->countOnLeaveTodayHandler->handle($userIds),
            absencesThisWeekCount: $this->countAbsencesThisWeekHandler->handle($userIds),
        );
    }
}
