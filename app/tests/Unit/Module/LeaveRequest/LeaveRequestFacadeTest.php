<?php

declare(strict_types=1);

use App\Module\LeaveRequest\LeaveRequestFacade;
use App\Module\LeaveRequest\UseCase\Command\RemoveLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Command\SaveLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Command\UpdateLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Query\CalculateWorkDaysQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountAbsencesThisWeekQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountAllPendingRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountAllRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountOnLeaveTodayQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\FindOnLeaveTodayQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetAllLeaveTypesQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetDailyAbsenceSummaryQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetDashboardStatsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveBalancesPerTypeQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForDatesGroupedByUserIdQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForDatesQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForUserQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveTypeQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetPendingLeaveRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetRecentLeaveRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetUpcomingLeaveRequestsQueryHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\Dashboard\DashboardStatsDTOFixture;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->calculateWorkDaysHandler = mock(CalculateWorkDaysQueryHandler::class);
    $this->getLeaveRequestsHandler = mock(GetLeaveRequestsForUserQueryHandler::class);
    $this->getLeaveRequestHandler = mock(GetLeaveRequestQueryHandler::class);
    $this->getLeaveTypeHandler = mock(GetLeaveTypeQueryHandler::class);
    $this->getUpcomingLeaveRequestsHandler = mock(GetUpcomingLeaveRequestsQueryHandler::class);
    $this->updateLeaveRequestCommandHandler = mock(UpdateLeaveRequestCommandHandler::class);
    $this->saveLeaveRequestCommandHandler = mock(SaveLeaveRequestCommandHandler::class);
    $this->getLeaveRequestForDatesHandler = mock(GetLeaveRequestsForDatesQueryHandler::class);
    $this->getLeaveRequestForDatesGroupedByUserIdHandler = mock(GetLeaveRequestsForDatesGroupedByUserIdQueryHandler::class);
    $this->removeRequestHandler = mock(RemoveLeaveRequestCommandHandler::class);
    $this->getPendingLeaveRequestsHandler = mock(GetPendingLeaveRequestsQueryHandler::class);
    $this->findOnLeaveTodayHandler = mock(FindOnLeaveTodayQueryHandler::class);
    $this->countOnLeaveTodayHandler = mock(CountOnLeaveTodayQueryHandler::class);
    $this->countAbsencesThisWeekHandler = mock(CountAbsencesThisWeekQueryHandler::class);
    $this->countAllPendingRequestsHandler = mock(CountAllPendingRequestsQueryHandler::class);
    $this->getDashboardStatsHandler = mock(GetDashboardStatsQueryHandler::class);
    $this->getDailyAbsenceSummaryHandler = mock(GetDailyAbsenceSummaryQueryHandler::class);
    $this->getLeaveBalancesPerTypeHandler = mock(GetLeaveBalancesPerTypeQueryHandler::class);
    $this->getRecentLeaveRequestsHandler = mock(GetRecentLeaveRequestsQueryHandler::class);
    $this->countAllRequestsHandler = mock(CountAllRequestsQueryHandler::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->getAllLeaveTypesHandler = mock(GetAllLeaveTypesQueryHandler::class);

    $this->facade = new LeaveRequestFacade(
        getCalculateWorkDaysHandler: $this->calculateWorkDaysHandler,
        getLeaveRequestsHandler: $this->getLeaveRequestsHandler,
        getLeaveRequestHandler: $this->getLeaveRequestHandler,
        getLeaveTypeHandler: $this->getLeaveTypeHandler,
        getUpcomingLeaveRequestsHandler: $this->getUpcomingLeaveRequestsHandler,
        updateLeaveRequestCommandHandler: $this->updateLeaveRequestCommandHandler,
        saveLeaveRequestCommandHandler: $this->saveLeaveRequestCommandHandler,
        getLeaveRequestForDatesHandler: $this->getLeaveRequestForDatesHandler,
        getLeaveRequestForDatesGroupedByUserIdHandler: $this->getLeaveRequestForDatesGroupedByUserIdHandler,
        removeRequestHandler: $this->removeRequestHandler,
        getPendingLeaveRequestsHandler: $this->getPendingLeaveRequestsHandler,
        findOnLeaveTodayHandler: $this->findOnLeaveTodayHandler,
        countOnLeaveTodayHandler: $this->countOnLeaveTodayHandler,
        countAbsencesThisWeekHandler: $this->countAbsencesThisWeekHandler,
        countAllPendingRequestsHandler: $this->countAllPendingRequestsHandler,
        getDashboardStatsHandler: $this->getDashboardStatsHandler,
        getDailyAbsenceSummaryHandler: $this->getDailyAbsenceSummaryHandler,
        getLeaveBalancesPerTypeHandler: $this->getLeaveBalancesPerTypeHandler,
        getRecentLeaveRequestsHandler: $this->getRecentLeaveRequestsHandler,
        countAllRequestsHandler: $this->countAllRequestsHandler,
        userFacade: $this->userFacade,
        getAllLeaveTypesHandler: $this->getAllLeaveTypesHandler,
    );
});

it('delegates calculateWorkDays to handler', function () {
    $query = new CalculateWorkdaysQuery(
        startDate: new DateTimeImmutable('2025-03-01'),
        endDate: new DateTimeImmutable('2025-03-10'),
        userWorkingDays: [1, 2, 3, 4, 5],
        holidayCalendarCountryCode: 'DE',
    );

    $this->calculateWorkDaysHandler
        ->expects('handle')
        ->once()
        ->with($query)
        ->andReturn(7);

    expect($this->facade->calculateWorkDays($query))->toBe(7);
});

it('delegates getLeaveRequestsForUser to handler', function () {
    $expected = [LeaveRequestDTOFixture::create()];

    $this->getLeaveRequestsHandler
        ->expects('handle')
        ->once()
        ->with('user-1', [LeaveRequestStatusEnum::Approved])
        ->andReturn($expected);

    $result = $this->facade->getLeaveRequestsForUser('user-1', [LeaveRequestStatusEnum::Approved]);

    expect($result)->toBe($expected);
});

it('passes empty array when status is null', function () {
    $this->getLeaveRequestsHandler
        ->expects('handle')
        ->once()
        ->with('user-1', [])
        ->andReturn([]);

    $this->facade->getLeaveRequestsForUser('user-1', null);
});

it('delegates getUpcomingLeaveRequests to handler', function () {
    $expected = [LeaveRequestDTOFixture::create()];

    $this->getUpcomingLeaveRequestsHandler
        ->expects('handle')
        ->once()
        ->with(null)
        ->andReturn($expected);

    expect($this->facade->getUpcomingLeaveRequests())->toBe($expected);
});

it('delegates getById to handler', function () {
    $expected = LeaveRequestDTOFixture::create();

    $this->getLeaveRequestHandler
        ->expects('handle')
        ->once()
        ->with('lr-1')
        ->andReturn($expected);

    expect($this->facade->getById('lr-1'))->toBe($expected);
});

it('delegates getLeaveTypeById to handler', function () {
    $expected = LeaveRequestTypeDTOFixture::create();

    $this->getLeaveTypeHandler
        ->expects('handle')
        ->once()
        ->with('lt-1')
        ->andReturn($expected);

    expect($this->facade->getLeaveTypeById('lt-1'))->toBe($expected);
});

it('delegates update to handler', function () {
    $dto = LeaveRequestDTOFixture::create();

    $this->updateLeaveRequestCommandHandler
        ->expects('handle')
        ->once()
        ->with($dto);

    $this->facade->update($dto);
});

it('updates and restores balance when leave type affects balance', function () {
    $dto = LeaveRequestDTOFixture::create([
        'leaveType' => LeaveRequestTypeDTOFixture::create(['isAffectingBalance' => true]),
        'workDays' => 5,
    ]);

    $this->updateLeaveRequestCommandHandler
        ->expects('handle')
        ->once()
        ->with($dto);

    $this->userFacade
        ->expects('updateUserCurrentLeaveBalance')
        ->once()
        ->with($dto->user->id, 5);

    $this->facade->updateAndRestoreBalanceIfNeeded($dto);
});

it('updates without restoring balance when leave type does not affect balance', function () {
    $dto = LeaveRequestDTOFixture::create([
        'leaveType' => LeaveRequestTypeDTOFixture::create(['isAffectingBalance' => false]),
    ]);

    $this->updateLeaveRequestCommandHandler
        ->expects('handle')
        ->once()
        ->with($dto);

    $this->userFacade
        ->shouldNotReceive('updateUserCurrentLeaveBalance');

    $this->facade->updateAndRestoreBalanceIfNeeded($dto);
});

it('calculates work days and saves leave request', function () {
    $userDTO = UserDTOFixture::create([
        'workingDays' => [1, 2, 3, 4, 5],
        'calendarCountryCode' => 'DE',
    ]);
    $leaveTypeDTO = LeaveRequestTypeDTOFixture::create();

    $command = new SaveLeaveRequestCommand(
        leaveRequestTypeDTO: $leaveTypeDTO,
        startDate: new DateTimeImmutable('2025-03-01'),
        endDate: new DateTimeImmutable('2025-03-10'),
        userDTO: $userDTO,
    );

    $this->calculateWorkDaysHandler
        ->expects('handle')
        ->once()
        ->withArgs(fn (CalculateWorkdaysQuery $q) => '2025-03-01' === $q->startDate->format('Y-m-d')
            && '2025-03-10' === $q->endDate->format('Y-m-d')
            && $q->userWorkingDays === [1, 2, 3, 4, 5]
            && 'DE' === $q->holidayCalendarCountryCode)
        ->andReturn(7);

    $this->saveLeaveRequestCommandHandler
        ->expects('handle')
        ->once()
        ->with($command, 7);

    $this->facade->save($command);
});

it('delegates remove to handler', function () {
    $dto = LeaveRequestDTOFixture::create();

    $this->removeRequestHandler
        ->expects('handle')
        ->once()
        ->with($dto);

    $this->facade->remove($dto);
});

it('delegates findOnLeaveToday to handler', function () {
    $expected = [LeaveRequestDTOFixture::create()];

    $this->findOnLeaveTodayHandler
        ->expects('handle')
        ->once()
        ->with(null)
        ->andReturn($expected);

    expect($this->facade->findOnLeaveToday())->toBe($expected);
});

it('delegates countOnLeaveToday to handler', function () {
    $this->countOnLeaveTodayHandler
        ->expects('handle')
        ->once()
        ->with(null)
        ->andReturn(3);

    expect($this->facade->countOnLeaveToday())->toBe(3);
});

it('delegates countAbsencesThisWeek to handler', function () {
    $this->countAbsencesThisWeekHandler
        ->expects('handle')
        ->once()
        ->with(null)
        ->andReturn(5);

    expect($this->facade->countAbsencesThisWeek())->toBe(5);
});

it('delegates countAllPendingRequests to handler', function () {
    $this->countAllPendingRequestsHandler
        ->expects('handle')
        ->once()
        ->with(null)
        ->andReturn(2);

    expect($this->facade->countAllPendingRequests())->toBe(2);
});

it('delegates getDashboardStats to handler', function () {
    $expected = DashboardStatsDTOFixture::create();

    $this->getDashboardStatsHandler
        ->expects('handle')
        ->once()
        ->with(null)
        ->andReturn($expected);

    expect($this->facade->getDashboardStats())->toBe($expected);
});

it('delegates getDailyAbsenceSummary to handler', function () {
    $weekStart = new DateTimeImmutable('2025-03-03');
    $expected = [];

    $this->getDailyAbsenceSummaryHandler
        ->expects('handle')
        ->once()
        ->with($weekStart)
        ->andReturn($expected);

    expect($this->facade->getDailyAbsenceSummary($weekStart))->toBe($expected);
});

it('delegates getLeaveBalancesPerType to handler', function () {
    $periodStart = new DateTimeImmutable('2025-01-01');
    $expected = [];

    $this->getLeaveBalancesPerTypeHandler
        ->expects('handle')
        ->once()
        ->with('user-1', $periodStart)
        ->andReturn($expected);

    expect($this->facade->getLeaveBalancesPerType('user-1', $periodStart))->toBe($expected);
});

it('delegates getRecentLeaveRequests to handler', function () {
    $expected = [LeaveRequestDTOFixture::create()];

    $this->getRecentLeaveRequestsHandler
        ->expects('handle')
        ->once()
        ->with(5, null)
        ->andReturn($expected);

    expect($this->facade->getRecentLeaveRequests())->toBe($expected);
});

it('delegates countAllRequests to handler', function () {
    $this->countAllRequestsHandler
        ->expects('handle')
        ->once()
        ->with(null)
        ->andReturn(42);

    expect($this->facade->countAllRequests())->toBe(42);
});

it('delegates getAllLeaveTypes to handler', function () {
    $expected = [LeaveRequestTypeDTOFixture::create()];

    $this->getAllLeaveTypesHandler
        ->expects('handle')
        ->once()
        ->andReturn($expected);

    expect($this->facade->getAllLeaveTypes())->toBe($expected);
});

it('delegates getPendingLeaveRequests to handler', function () {
    $date = new DateTimeImmutable('2025-03-01');
    $expected = [LeaveRequestDTOFixture::create()];

    $this->getPendingLeaveRequestsHandler
        ->expects('handle')
        ->once()
        ->with($date)
        ->andReturn($expected);

    expect($this->facade->getPendingLeaveRequests($date))->toBe($expected);
});

it('delegates getLeaveRequestsForDates to handler', function () {
    $start = new DateTimeImmutable('2025-03-01');
    $end = new DateTimeImmutable('2025-03-31');
    $statuses = [LeaveRequestStatusEnum::Approved];
    $expected = [LeaveRequestDTOFixture::create()];

    $this->getLeaveRequestForDatesHandler
        ->expects('handle')
        ->once()
        ->with($start, $end, $statuses)
        ->andReturn($expected);

    expect($this->facade->getLeaveRequestsForDates($start, $end, $statuses))->toBe($expected);
});

it('delegates getLeaveRequestsForDatesGroupedByUserId to handler', function () {
    $start = new DateTimeImmutable('2025-03-01');
    $end = new DateTimeImmutable('2025-03-31');
    $statuses = [LeaveRequestStatusEnum::Approved];
    $expected = ['user-1' => [LeaveRequestDTOFixture::create()]];

    $this->getLeaveRequestForDatesGroupedByUserIdHandler
        ->expects('handle')
        ->once()
        ->with($start, $end, $statuses)
        ->andReturn($expected);

    expect($this->facade->getLeaveRequestsForDatesGroupedByUserId($start, $end, $statuses))->toBe($expected);
});
