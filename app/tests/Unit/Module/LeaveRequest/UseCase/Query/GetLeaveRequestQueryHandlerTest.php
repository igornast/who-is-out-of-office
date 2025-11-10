<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveRequestQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new GetLeaveRequestQueryHandler(repository: $this->repository);
});

it('finds leave request by id', function () {
    $leaveRequestId = '123e4567-e89b-12d3-a456-426614174000';
    $expectedLeaveRequest = LeaveRequestDTOFixture::create(['id' => Uuid::fromString($leaveRequestId)]);

    $this->repository
        ->expects('findById')
        ->once()
        ->with($leaveRequestId)
        ->andReturn($expectedLeaveRequest);

    $result = $this->handler->handle($leaveRequestId);

    expect($result)
        ->toBe($expectedLeaveRequest)
        ->and($result->id->toString())->toBe($leaveRequestId);
});

it('returns null when leave request not found', function () {
    $leaveRequestId = '999e4567-e89b-12d3-a456-426614174000';

    $this->repository
        ->expects('findById')
        ->once()
        ->with($leaveRequestId)
        ->andReturn(null);

    $result = $this->handler->handle($leaveRequestId);

    expect($result)->toBeNull();
});
