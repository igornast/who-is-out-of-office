<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\DTO\LeaveRequest\Query\CalculateWorkdaysQuery;
use App\Shared\Enum\LeaveRequestStatusEnum;

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
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return array{string, LeaveRequestDTO[]}
     */
    public function getLeaveRequestsForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array;

    public function remove(LeaveRequestDTO $leaveRequestDTO): void;
}
