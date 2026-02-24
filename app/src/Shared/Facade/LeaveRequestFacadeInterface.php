<?php

declare(strict_types=1);

namespace App\Shared\Facade;

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
     * @return LeaveRequestDTO[]
     */
    public function getUpcomingLeaveRequests(): array;

    public function remove(LeaveRequestDTO $leaveRequestDTO): void;

    /**
     * @return LeaveRequestDTO[]
     */
    public function findOnLeaveToday(): array;

    public function countOnLeaveToday(): int;

    public function countAbsencesThisWeek(): int;

    public function countAllPendingRequests(): int;
}
