<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

interface LeaveRequestFacadeInterface
{
    public function calculateWorkDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int;

    public function getById(string $id): ?LeaveRequestDTO;

    public function update(LeaveRequestDTO $leaveRequestDTO): void;

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return LeaveRequestDTO[]
     */
    public function getLeaveRequestsForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array;
}
