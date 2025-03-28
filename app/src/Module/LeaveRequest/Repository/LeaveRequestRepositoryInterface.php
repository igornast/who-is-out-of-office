<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Repository;

interface LeaveRequestRepositoryInterface
{
    public function findForUser(string $userId, array $status): array;

    public function findUpcomingApprovedAbsences(int $limit = 4): array;
}
