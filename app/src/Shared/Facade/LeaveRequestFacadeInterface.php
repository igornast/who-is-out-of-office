<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\LeaveRequestDTO;

interface LeaveRequestFacadeInterface
{
    public function calculateWorkDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int;

    public function getById(string $id): ?LeaveRequestDTO;

    public function update(LeaveRequestDTO $leaveRequestDTO): void;

    /**
     * @return LeaveRequestDTO[]
     */
    public function getApprovedLeaveRequestsForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;
}
