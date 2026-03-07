<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Dashboard\DailyAbsenceSummaryDTO;
use App\Shared\DTO\Dashboard\DashboardStatsDTO;
use App\Shared\DTO\Dashboard\LeaveBalanceDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;

interface LeaveRequestFacadeInterface
{
    public function calculateWorkDays(CalculateWorkdaysQuery $query): int;

    public function getById(string $id): ?LeaveRequestDTO;

    public function getLeaveTypeById(string $id): ?LeaveRequestTypeDTO;

    public function update(LeaveRequestDTO $leaveRequestDTO): void;

    public function updateAndRestoreBalanceIfNeeded(LeaveRequestDTO $leaveRequestDTO): void;

    public function save(SaveLeaveRequestCommand $command): void;

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return LeaveRequestDTO[]
     */
    public function getLeaveRequestsForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array;

    /**
     * @return LeaveRequestDTO[]
     */
    public function getPendingLeaveRequests(\DateTimeImmutable $createdBefore): array;

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return array{string, LeaveRequestDTO[]}
     */
    public function getLeaveRequestsForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array;

    /**
     * @param LeaveRequestStatusEnum[]|null $status
     *
     * @return LeaveRequestDTO[]
     */
    public function getLeaveRequestsForUser(string $userId, ?array $status): array;

    /**
     * @param string[]|null $userIds
     *
     * @return LeaveRequestDTO[]
     */
    public function getUpcomingLeaveRequests(?array $userIds = null): array;

    public function remove(LeaveRequestDTO $leaveRequestDTO): void;

    /**
     * @param string[]|null $userIds
     *
     * @return LeaveRequestDTO[]
     */
    public function findOnLeaveToday(?array $userIds = null): array;

    /**
     * @param string[]|null $userIds
     */
    public function countOnLeaveToday(?array $userIds = null): int;

    /**
     * @param string[]|null $userIds
     */
    public function countAbsencesThisWeek(?array $userIds = null): int;

    /**
     * @param string[]|null $userIds
     */
    public function countAllPendingRequests(?array $userIds = null): int;

    /**
     * @param string[]|null $userIds
     */
    public function getDashboardStats(?array $userIds = null): DashboardStatsDTO;

    /**
     * @return DailyAbsenceSummaryDTO[]
     */
    public function getDailyAbsenceSummary(?\DateTimeImmutable $weekStart = null): array;

    /**
     * @return LeaveBalanceDTO[]
     */
    public function getLeaveBalancesPerType(string $userId, \DateTimeImmutable $periodStart): array;

    /**
     * @param string[]|null $userIds
     *
     * @return LeaveRequestDTO[]
     */
    public function getRecentLeaveRequests(int $limit = 5, ?array $userIds = null): array;

    /**
     * @param string[]|null $userIds
     */
    public function countAllRequests(?array $userIds = null): int;

    /**
     * @return LeaveRequestTypeDTO[]
     */
    public function getAllLeaveTypes(): array;
}
