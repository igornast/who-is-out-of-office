<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest;

use App\Module\LeaveRequest\UseCase\Query\GetCalculateWorkDaysHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsHandler;
use App\Module\LeaveRequest\UseCase\Query\GetUpcomingLeaveRequestsHandler;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;

final class LeaveRequestFacade implements LeaveRequestFacadeInterface
{
    public function __construct(
        private readonly GetCalculateWorkDaysHandler $getCalculateWorkDaysHandler,
        private readonly GetLeaveRequestsHandler $getLeaveRequestsHandler,
        private readonly GetUpcomingLeaveRequestsHandler $getUpcomingLeaveRequestsHandler,
    ) {
    }

    public function calculateWorkDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        return $this->getCalculateWorkDaysHandler->handle($startDate, $endDate);
    }

    /**
     * @param LeaveRequestStatusEnum[]|null $status
     *
     * @return LeaveRequestDTO[]
     */
    public function getLeaveRequestsForUser(string $userId, ?array $status): array
    {
        return $this->getLeaveRequestsHandler->handle($userId, $status ?? []);
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function getUpcomingLeaveRequests(): array
    {
        return $this->getUpcomingLeaveRequestsHandler->handle();
    }
}
