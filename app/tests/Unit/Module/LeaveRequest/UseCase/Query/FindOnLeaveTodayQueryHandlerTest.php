<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\FindOnLeaveTodayQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new FindOnLeaveTodayQueryHandler(repository: $this->repository);
});

it('returns leave requests for users on leave today', function () {
    $expectedLeaveRequests = [
        LeaveRequestDTOFixture::create(),
        LeaveRequestDTOFixture::create(),
    ];

    $this->repository
        ->expects('findOnLeaveToday')
        ->once()
        ->andReturn($expectedLeaveRequests);

    $result = $this->handler->handle();

    expect($result)->toBe($expectedLeaveRequests)
        ->and($result)->toHaveCount(2);
});

it('returns empty array when no one is on leave today', function () {
    $this->repository
        ->expects('findOnLeaveToday')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});
