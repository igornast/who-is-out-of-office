<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForDatesGroupedByUserIdQueryHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetLeaveRequestsForDatesGroupedByUserIdQueryHandler(repository: $this->repository);
});

it('finds leave requests grouped by user id', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $statuses = [LeaveRequestStatusEnum::Approved];
    $expectedGroupedRequests = [
        'user1' => [
            LeaveRequestDTOFixture::create(),
            LeaveRequestDTOFixture::create(),
        ],
        'user2' => [
            LeaveRequestDTOFixture::create(),
        ],
    ];

    $this->repository
        ->expects('findForDatesGroupedByUserId')
        ->once()
        ->with($startDate, $endDate, $statuses)
        ->andReturn($expectedGroupedRequests);

    $result = $this->handler->handle($startDate, $endDate, $statuses);

    expect($result)->toBe($expectedGroupedRequests)
        ->and($result)->toHaveCount(2)
        ->and($result)->toHaveKey('user1')
        ->and($result)->toHaveKey('user2');
});

it('handles multiple statuses', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $statuses = [LeaveRequestStatusEnum::Approved, LeaveRequestStatusEnum::Pending];
    $expectedGroupedRequests = [
        'user1' => [LeaveRequestDTOFixture::create()],
    ];

    $this->repository
        ->expects('findForDatesGroupedByUserId')
        ->once()
        ->with($startDate, $endDate, $statuses)
        ->andReturn($expectedGroupedRequests);

    $result = $this->handler->handle($startDate, $endDate, $statuses);

    expect($result)->toBe($expectedGroupedRequests);
});

it('returns empty array when no leave requests found', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $statuses = [LeaveRequestStatusEnum::Approved];

    $this->repository
        ->expects('findForDatesGroupedByUserId')
        ->once()
        ->with($startDate, $endDate, $statuses)
        ->andReturn([]);

    $result = $this->handler->handle($startDate, $endDate, $statuses);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});
