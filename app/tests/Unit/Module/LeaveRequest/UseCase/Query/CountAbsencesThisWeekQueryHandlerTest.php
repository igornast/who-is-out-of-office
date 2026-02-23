<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\CountAbsencesThisWeekQueryHandler;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new CountAbsencesThisWeekQueryHandler(repository: $this->repository);
});

it('returns count of absences this week', function () {
    $this->repository
        ->expects('countAbsencesThisWeek')
        ->once()
        ->andReturn(5);

    $result = $this->handler->handle();

    expect($result)->toBe(5);
});

it('returns zero when no absences this week', function () {
    $this->repository
        ->expects('countAbsencesThisWeek')
        ->once()
        ->andReturn(0);

    $result = $this->handler->handle();

    expect($result)->toBe(0);
});
