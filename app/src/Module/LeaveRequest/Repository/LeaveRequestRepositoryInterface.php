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

    public function countOnLeaveToday(): int;

    public function countAbsencesThisWeek(): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
