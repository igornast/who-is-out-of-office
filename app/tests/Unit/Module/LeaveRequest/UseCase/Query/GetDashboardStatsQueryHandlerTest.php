<?php

declare(strict_types=1);

use App\Module\LeaveRequest\UseCase\Query\CountAbsencesThisWeekQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountAllPendingRequestsQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\CountOnLeaveTodayQueryHandler;
use App\Module\LeaveRequest\UseCase\Query\GetDashboardStatsQueryHandler;
use App\Shared\DTO\Dashboard\DashboardStatsDTO;

beforeEach(function (): void {
    $this->countAllPendingRequestsHandler = mock(CountAllPendingRequestsQueryHandler::class);
    $this->countOnLeaveTodayHandler = mock(CountOnLeaveTodayQueryHandler::class);
    $this->countAbsencesThisWeekHandler = mock(CountAbsencesThisWeekQueryHandler::class);

    $this->handler = new GetDashboardStatsQueryHandler(
        countAllPendingRequestsHandler: $this->countAllPendingRequestsHandler,
        countOnLeaveTodayHandler: $this->countOnLeaveTodayHandler,
        countAbsencesThisWeekHandler: $this->countAbsencesThisWeekHandler,
    );
});

it('returns dashboard stats DTO with all counts', function () {
    $this->countAllPendingRequestsHandler
        ->expects('handle')
        ->once()
        ->andReturn(3);

    $this->countOnLeaveTodayHandler
        ->expects('handle')
        ->once()
        ->andReturn(2);

    $this->countAbsencesThisWeekHandler
        ->expects('handle')
        ->once()
        ->andReturn(7);

    $result = $this->handler->handle();

    expect($result)
        ->toBeInstanceOf(DashboardStatsDTO::class)
        ->and($result->pendingRequestsCount)->toBe(3)
        ->and($result->onLeaveTodayCount)->toBe(2)
        ->and($result->absencesThisWeekCount)->toBe(7);
});

it('returns zeros when no data exists', function () {
    $this->countAllPendingRequestsHandler
        ->expects('handle')
        ->once()
        ->andReturn(0);

    $this->countOnLeaveTodayHandler
        ->expects('handle')
        ->once()
        ->andReturn(0);

    $this->countAbsencesThisWeekHandler
        ->expects('handle')
        ->once()
        ->andReturn(0);

    $result = $this->handler->handle();

    expect($result->pendingRequestsCount)->toBe(0)
        ->and($result->onLeaveTodayCount)->toBe(0)
        ->and($result->absencesThisWeekCount)->toBe(0);
});

it('passes user IDs filter to sub-handlers for team-scoped stats', function () {
    $teamUserIds = ['user-1', 'user-2'];

    $this->countAllPendingRequestsHandler
        ->expects('handle')
        ->with($teamUserIds)
        ->once()
        ->andReturn(1);

    $this->countOnLeaveTodayHandler
        ->expects('handle')
        ->with($teamUserIds)
        ->once()
        ->andReturn(1);

    $this->countAbsencesThisWeekHandler
        ->expects('handle')
        ->with($teamUserIds)
        ->once()
        ->andReturn(2);

    $result = $this->handler->handle($teamUserIds);

    expect($result->pendingRequestsCount)->toBe(1)
        ->and($result->onLeaveTodayCount)->toBe(1)
        ->and($result->absencesThisWeekCount)->toBe(2);
});
