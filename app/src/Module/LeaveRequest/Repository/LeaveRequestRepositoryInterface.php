<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Repository;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

interface LeaveRequestRepositoryInterface
{
    /**
     * @param LeaveRequestStatusEnum[] $status
     *
     * @return LeaveRequestDTO[]
     */
    public function findForUser(string $userId, array $status): array;

    /**
     * @param string[]|null $userIds
     *
     * @return LeaveRequestDTO[]
     */
    public function findUpcomingApprovedAbsences(int $limit = 4, ?array $userIds = null): array;

    public function findById(string $id): ?LeaveRequestDTO;

    public function saveLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void;

    public function update(LeaveRequestDTO $leaveRequestDTO): void;

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return LeaveRequestDTO[]
     */
    public function findForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array;

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return array{string, LeaveRequestDTO[]}
     */
    public function findForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array;

    public function delete(LeaveRequestDTO $leaveRequestDTO): void;

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
     * @return list<array<string, mixed>>
     */
    public function findUsedDaysPerTypeForUser(string $userId, \DateTimeImmutable $periodStart): array;

    /**
     * @param string[]|null $userIds
     *
     * @return LeaveRequestDTO[]
     */
    public function findRecentRequests(int $limit = 5, ?array $userIds = null): array;

    /**
     * @param string[]|null $userIds
     */
    public function countAllRequests(?array $userIds = null): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
