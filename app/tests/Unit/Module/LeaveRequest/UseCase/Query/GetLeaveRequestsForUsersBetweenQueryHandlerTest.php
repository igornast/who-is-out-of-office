<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForUsersBetweenQueryHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetLeaveRequestsForUsersBetweenQueryHandler(
        leaveRequestRepository: $this->repository
    );
});

it('returns leave requests found by repository for the given users, statuses and date range', function () {
    $startDate = new DateTimeImmutable('2026-01-01');
    $endDate = new DateTimeImmutable('2026-12-31');
    $userIds = ['user-1', 'user-2'];
    $statuses = [LeaveRequestStatusEnum::Approved, LeaveRequestStatusEnum::Pending];

    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $this->repository
        ->expects('findForUsersBetweenDates')
        ->once()
        ->with($userIds, $statuses, $startDate, $endDate)
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle($userIds, $statuses, $startDate, $endDate);

    expect($result)->toBe($expectedLeaveRequests)
        ->and($result)->toHaveCount(3);
});

it('returns empty array when repository finds no leave requests', function () {
    $startDate = new DateTimeImmutable('2026-01-01');
    $endDate = new DateTimeImmutable('2026-12-31');

    $this->repository
        ->expects('findForUsersBetweenDates')
        ->once()
        ->with([], [], $startDate, $endDate)
        ->andReturn([]);

    $result = $this->handler->handle([], [], $startDate, $endDate);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});
