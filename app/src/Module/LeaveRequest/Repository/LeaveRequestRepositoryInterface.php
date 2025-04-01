<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Repository;

use App\Shared\DTO\LeaveRequestDTO;
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

    public function update(LeaveRequestDTO $leaveRequestDTO): void;
}
