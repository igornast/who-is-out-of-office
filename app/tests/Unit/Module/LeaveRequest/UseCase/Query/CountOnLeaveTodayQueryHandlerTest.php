<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\CountOnLeaveTodayQueryHandler;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new CountOnLeaveTodayQueryHandler(repository: $this->repository);
});

it('returns count of users on leave today', function () {
    $this->repository
        ->expects('countOnLeaveToday')
        ->once()
        ->andReturn(3);

    $result = $this->handler->handle();

    expect($result)->toBe(3);
});

it('returns zero when no one is on leave today', function () {
    $this->repository
        ->expects('countOnLeaveToday')
        ->once()
        ->andReturn(0);

    $result = $this->handler->handle();

    expect($result)->toBe(0);
});
