<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Repository\LeaveRequestRepository;
use App\Module\LeaveRequest\UseCase\Query\GetPendingLeaveRequestsQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepository::class);

    $this->handler = new GetPendingLeaveRequestsQueryHandler(leaveRequestRepository: $this->repository);
});

it('finds pending leave requests created before given date', function () {
    $createdBefore = new DateTimeImmutable('2025-01-01 12:00:00');
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $this->repository
        ->expects('findPendingCreatedBefore')
        ->once()
        ->with($createdBefore)
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle($createdBefore);

    expect($result)->toBe($expectedLeaveRequests)
        ->and($result)->toHaveCount(2);
});

it('returns empty array when no pending requests found', function () {
    $createdBefore = new DateTimeImmutable('2025-01-01 12:00:00');

    $this->repository
        ->expects('findPendingCreatedBefore')
        ->once()
        ->with($createdBefore)
        ->andReturn([]);

    $result = $this->handler->handle($createdBefore);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

it('correctly passes the date parameter to repository', function () {
    $createdBefore = new DateTimeImmutable('2024-12-15 08:30:00');

    $this->repository
        ->expects('findPendingCreatedBefore')
        ->once()
        ->withArgs(fn (DateTimeImmutable $date) => $date->getTimestamp() === $createdBefore->getTimestamp())
        ->andReturn([]);

    $this->handler->handle($createdBefore);
});
