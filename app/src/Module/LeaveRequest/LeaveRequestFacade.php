<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest;

use App\Module\LeaveRequest\UseCase\Command\RemoveLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Command\UpdateLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Query\CalculateWorkDaysQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForDatesQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForUserQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetUpcomingLeaveRequestsQueryHandler;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\LeaveRequest\Query\CalculateWorkdaysQuery;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;

final class LeaveRequestFacade implements LeaveRequestFacadeInterface
{
    public function __construct(
        private readonly CalculateWorkDaysQueryHandler $getCalculateWorkDaysHandler,
        private readonly GetLeaveRequestsForUserQueryHandler $getLeaveRequestsHandler,
        private readonly GetLeaveRequestQueryHandler $getLeaveRequestHandler,
        private readonly GetUpcomingLeaveRequestsQueryHandler $getUpcomingLeaveRequestsHandler,
        private readonly UpdateLeaveRequestCommandHandler $updateLeaveRequestCommandHandler,
        private readonly GetLeaveRequestsForDatesQueryHandler $getLeaveRequestForDatesHandler,
        private readonly RemoveLeaveRequestCommandHandler $removeRequestHandler,
    ) {
    }

    public function calculateWorkDays(CalculateWorkdaysQuery $query): int
    {
        return $this->getCalculateWorkDaysHandler->handle($query);
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

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return LeaveRequestDTO[]
     */
    public function getLeaveRequestsForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array
    {
        return $this->getLeaveRequestForDatesHandler->handle($startDate, $endDate, $statuses);
    }

    public function remove(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->removeRequestHandler->handle($leaveRequestDTO);
    }
}
