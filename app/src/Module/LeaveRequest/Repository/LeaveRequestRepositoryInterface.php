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
     * @return LeaveRequestDTO[]
     */
    public function findUpcomingApprovedAbsences(int $limit = 4): array;

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
     * @return LeaveRequestDTO[]
     */
    public function findOnLeaveToday(): array;

    public function countOnLeaveToday(): int;

    public function countAbsencesThisWeek(): int;

    public function countAllPendingRequests(): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function findUsedDaysPerTypeForUser(string $userId, \DateTimeImmutable $periodStart): array;

    /**
     * @return LeaveRequestDTO[]
     */
    public function findRecentRequests(int $limit = 5): array;

    public function countAllRequests(): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
