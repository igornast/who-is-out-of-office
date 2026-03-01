<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\CountAllRequestsQueryHandler;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new CountAllRequestsQueryHandler(repository: $this->repository);
});

it('returns count of all requests', function () {
    $this->repository
        ->expects('countAllRequests')
        ->once()
        ->andReturn(42);

    $result = $this->handler->handle();

    expect($result)->toBe(42);
});

it('returns zero when there are no requests', function () {
    $this->repository
        ->expects('countAllRequests')
        ->once()
        ->andReturn(0);

    $result = $this->handler->handle();

    expect($result)->toBe(0);
});
