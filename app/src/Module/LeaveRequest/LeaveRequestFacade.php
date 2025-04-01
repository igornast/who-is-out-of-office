<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest;

use App\Module\LeaveRequest\UseCase\Command\UpdateLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Query\GetCalculateWorkDaysQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetUpcomingLeaveRequestsQueryHandler;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;

final class LeaveRequestFacade implements LeaveRequestFacadeInterface
{
    public function __construct(
        private readonly GetCalculateWorkDaysQueryHandler $getCalculateWorkDaysHandler,
        private readonly GetLeaveRequestsQueryHandler $getLeaveRequestsHandler,
        private readonly GetLeaveRequestQueryHandler $getLeaveRequestHandler,
        private readonly GetUpcomingLeaveRequestsQueryHandler $getUpcomingLeaveRequestsHandler,
        private readonly UpdateLeaveRequestCommandHandler $updateLeaveRequestCommandHandler,
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

    public function getById(string $id): ?LeaveRequestDTO
    {
        return $this->getLeaveRequestHandler->handle($id);
    }

    public function update(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->updateLeaveRequestCommandHandler->handle($leaveRequestDTO);
    }
}
