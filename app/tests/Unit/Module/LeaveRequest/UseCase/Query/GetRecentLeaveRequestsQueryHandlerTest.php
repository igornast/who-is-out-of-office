<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetRecentLeaveRequestsQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetRecentLeaveRequestsQueryHandler(repository: $this->repository);
});

it('returns recent leave requests', function () {
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $this->repository
        ->expects('findRecentRequests')
        ->with(5)
        ->once()
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle();

    expect($result)->toBe($expectedLeaveRequests)
        ->and($result)->toHaveCount(3);
});

it('returns empty array when no leave requests exist', function () {
    $this->repository
        ->expects('findRecentRequests')
        ->with(5)
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

it('passes custom limit to repository', function () {
    $this->repository
        ->expects('findRecentRequests')
        ->with(10)
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle(10);

    expect($result)->toBeArray();
});
