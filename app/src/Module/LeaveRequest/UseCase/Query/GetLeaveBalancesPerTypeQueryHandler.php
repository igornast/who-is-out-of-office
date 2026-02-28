<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\Dashboard\LeaveBalanceDTO;

class GetLeaveBalancesPerTypeQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @return LeaveBalanceDTO[]
     */
    public function handle(string $userId, \DateTimeImmutable $periodStart): array
    {
        $rows = $this->repository->findUsedDaysPerTypeForUser($userId, $periodStart);

        return array_map(
            static fn (array $row) => new LeaveBalanceDTO(
                leaveTypeName: $row['leave_type_name'],
                leaveTypeIcon: $row['leave_type_icon'],
                leaveTypeBackgroundColor: $row['background_color'],
                leaveTypeBorderColor: $row['border_color'],
                leaveTypeTextColor: $row['text_color'],
                isAffectingBalance: (bool) $row['is_affecting_balance'],
                usedDays: (int) $row['used_days'],
            ),
            $rows
        );
    }
}
