<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForDatesQueryHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->leaveRequestRepository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetLeaveRequestsForDatesQueryHandler(leaveRequestRepository: $this->leaveRequestRepository);
});

it('finds leave requests for date range with specific statuses', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $statuses = [LeaveRequestStatusEnum::Approved];
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $this->leaveRequestRepository
        ->expects('findForDates')
        ->once()
        ->with($startDate, $endDate, $statuses)
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle($startDate, $endDate, $statuses);

    expect($result)->toBe($expectedLeaveRequests)
        ->and($result)->toHaveCount(2);
});

it('handles multiple statuses', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $statuses = [LeaveRequestStatusEnum::Approved, LeaveRequestStatusEnum::Pending];
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
    ];

    $this->leaveRequestRepository
        ->expects('findForDates')
        ->once()
        ->with($startDate, $endDate, $statuses)
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle($startDate, $endDate, $statuses);

    expect($result)->toBe($expectedLeaveRequests);
});

it('returns empty array when no leave requests found', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $statuses = [LeaveRequestStatusEnum::Approved];

    $this->leaveRequestRepository
        ->expects('findForDates')
        ->once()
        ->with($startDate, $endDate, $statuses)
        ->andReturn([]);

    $result = $this->handler->handle($startDate, $endDate, $statuses);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});
