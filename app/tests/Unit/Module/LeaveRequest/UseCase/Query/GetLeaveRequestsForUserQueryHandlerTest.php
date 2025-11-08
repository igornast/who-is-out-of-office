<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestsForUserQueryHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetLeaveRequestsForUserQueryHandler(repository: $this->repository);
});

it('finds leave requests for user with specific statuses', function () {
    $userId = '123';
    $statuses = [LeaveRequestStatusEnum::Approved, LeaveRequestStatusEnum::Pending];
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $this->repository
        ->expects('findForUser')
        ->once()
        ->with($userId, $statuses)
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle($userId, $statuses);

    expect($result)->toBe($expectedLeaveRequests)
        ->and($result)->toHaveCount(2);
});

it('uses all statuses when empty array provided', function () {
    $userId = '123';
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
    ];

    $this->repository
        ->expects('findForUser')
        ->once()
        ->with($userId, LeaveRequestStatusEnum::cases())
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle($userId, []);

    expect($result)->toBe($expectedLeaveRequests);
});

it('returns empty array when no leave requests found', function () {
    $userId = '123';
    $statuses = [LeaveRequestStatusEnum::Approved];

    $this->repository
        ->expects('findForUser')
        ->once()
        ->with($userId, $statuses)
        ->andReturn([]);

    $result = $this->handler->handle($userId, $statuses);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});
