<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\CountAllPendingRequestsQueryHandler;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new CountAllPendingRequestsQueryHandler(repository: $this->repository);
});

it('returns count of all pending requests', function () {
    $this->repository
        ->expects('countAllPendingRequests')
        ->once()
        ->andReturn(5);

    $result = $this->handler->handle();

    expect($result)->toBe(5);
});

it('returns zero when there are no pending requests', function () {
    $this->repository
        ->expects('countAllPendingRequests')
        ->once()
        ->andReturn(0);

    $result = $this->handler->handle();

    expect($result)->toBe(0);
});
