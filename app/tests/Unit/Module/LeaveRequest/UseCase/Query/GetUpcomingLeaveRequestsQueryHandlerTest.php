<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetUpcomingLeaveRequestsQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetUpcomingLeaveRequestsQueryHandler(repository: $this->repository);
});

it('finds upcoming approved leave requests', function () {
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $this->repository
        ->expects('findUpcomingApprovedAbsences')
        ->once()
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle();

    expect($result)->toBe($expectedLeaveRequests)
        ->and($result)->toHaveCount(3);
});

it('returns empty array when no upcoming leave requests', function () {
    $this->repository
        ->expects('findUpcomingApprovedAbsences')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});
