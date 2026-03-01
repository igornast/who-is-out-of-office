<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest;

use App\Module\LeaveRequest\UseCase\Command\RemoveLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Command\SaveLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Command\UpdateLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Query\CalculateWorkDaysQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountAbsencesThisWeekQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountAllPendingRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountOnLeaveTodayQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\FindOnLeaveTodayQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetDailyAbsenceSummaryQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveBalancesPerTypeQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountAllRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForDatesGroupedByUserIdQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForDatesQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForUserQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveTypeQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetPendingLeaveRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetRecentLeaveRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetUpcomingLeaveRequestsQueryHandler;
use App\Shared\DTO\Dashboard\LeaveBalanceDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;

final class LeaveRequestFacade implements LeaveRequestFacadeInterface
{
    public function __construct(
        private readonly CalculateWorkDaysQueryHandler $getCalculateWorkDaysHandler,
        private readonly GetLeaveRequestsForUserQueryHandler $getLeaveRequestsHandler,
        private readonly GetLeaveRequestQueryHandler $getLeaveRequestHandler,
        private readonly GetLeaveTypeQueryHandler $getLeaveTypeHandler,
        private readonly GetUpcomingLeaveRequestsQueryHandler $getUpcomingLeaveRequestsHandler,
        private readonly UpdateLeaveRequestCommandHandler $updateLeaveRequestCommandHandler,
        private readonly SaveLeaveRequestCommandHandler $saveLeaveRequestCommandHandler,
        private readonly GetLeaveRequestsForDatesQueryHandler $getLeaveRequestForDatesHandler,
        private readonly GetLeaveRequestsForDatesGroupedByUserIdQueryHandler $getLeaveRequestForDatesGroupedByUserIdHandler,
        private readonly RemoveLeaveRequestCommandHandler $removeRequestHandler,
        private readonly GetPendingLeaveRequestsQueryHandler $getPendingLeaveRequestsHandler,
        private readonly FindOnLeaveTodayQueryHandler $findOnLeaveTodayHandler,
        private readonly CountOnLeaveTodayQueryHandler $countOnLeaveTodayHandler,
        private readonly CountAbsencesThisWeekQueryHandler $countAbsencesThisWeekHandler,
        private readonly CountAllPendingRequestsQueryHandler $countAllPendingRequestsHandler,
        private readonly GetDailyAbsenceSummaryQueryHandler $getDailyAbsenceSummaryHandler,
        private readonly GetLeaveBalancesPerTypeQueryHandler $getLeaveBalancesPerTypeHandler,
        private readonly GetRecentLeaveRequestsQueryHandler $getRecentLeaveRequestsHandler,
        private readonly CountAllRequestsQueryHandler $countAllRequestsHandler,
        private readonly UserFacadeInterface $userFacade,
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

    public function getLeaveTypeById(string $id): ?LeaveRequestTypeDTO
    {
        return $this->getLeaveTypeHandler->handle($id);
    }

    public function update(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->updateLeaveRequestCommandHandler->handle($leaveRequestDTO);
    }

    public function updateAndRestoreBalanceIfNeeded(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->update($leaveRequestDTO);

        if ($leaveRequestDTO->leaveType->isAffectingBalance) {
            $this->userFacade->updateUserCurrentLeaveBalance(
                $leaveRequestDTO->user->id,
                $leaveRequestDTO->workDays,
            );
        }
    }

    public function save(SaveLeaveRequestCommand $command): void
    {
        $query = new CalculateWorkdaysQuery(
            startDate: $command->startDate,
            endDate: $command->endDate,
            userWorkingDays: $command->userDTO->workingDays,
            holidayCalendarCountryCode: $command->userDTO->calendarCountryCode
        );

        $workDays = $this->calculateWorkDays($query);

        $this->saveLeaveRequestCommandHandler->handle($command, $workDays);
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

    /**
     * @return LeaveRequestDTO[]
     */
    public function getPendingLeaveRequests(\DateTimeImmutable $createdBefore): array
    {
        return $this->getPendingLeaveRequestsHandler->handle($createdBefore);
    }

    /**
     * @param LeaveRequestStatusEnum[] $statuses
     *
     * @return array{string, LeaveRequestDTO[]}
     */
    public function getLeaveRequestsForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array
    {
        return $this->getLeaveRequestForDatesGroupedByUserIdHandler->handle($startDate, $endDate, $statuses);
    }

    public function remove(LeaveRequestDTO $leaveRequestDTO): void
    {
        $this->removeRequestHandler->handle($leaveRequestDTO);
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function findOnLeaveToday(): array
    {
        return $this->findOnLeaveTodayHandler->handle();
    }

    public function countOnLeaveToday(): int
    {
        return $this->countOnLeaveTodayHandler->handle();
    }

    public function countAbsencesThisWeek(): int
    {
        return $this->countAbsencesThisWeekHandler->handle();
    }

    public function countAllPendingRequests(): int
    {
        return $this->countAllPendingRequestsHandler->handle();
    }

    /**
     * @return \App\Shared\DTO\Dashboard\DailyAbsenceSummaryDTO[]
     */
    public function getDailyAbsenceSummary(?\DateTimeImmutable $weekStart = null): array
    {
        return $this->getDailyAbsenceSummaryHandler->handle($weekStart);
    }

    /**
     * @return LeaveBalanceDTO[]
     */
    public function getLeaveBalancesPerType(string $userId, \DateTimeImmutable $periodStart): array
    {
        return $this->getLeaveBalancesPerTypeHandler->handle($userId, $periodStart);
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function getRecentLeaveRequests(int $limit = 5): array
    {
        return $this->getRecentLeaveRequestsHandler->handle($limit);
    }

    public function countAllRequests(): int
    {
        return $this->countAllRequestsHandler->handle();
    }
}
