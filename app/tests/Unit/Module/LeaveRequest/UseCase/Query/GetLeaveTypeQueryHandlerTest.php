<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestTypeRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveTypeQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestTypeRepositoryInterface::class);

    $this->handler = new GetLeaveTypeQueryHandler(repository: $this->repository);
});

it('finds leave type by id', function () {
    $leaveTypeId = '123e4567-e89b-12d3-a456-426614174000';
    $expectedLeaveType = LeaveRequestTypeDTOFixture::create(['id' => Uuid::fromString($leaveTypeId)]);

    $this->repository
        ->expects('findById')
        ->once()
        ->with($leaveTypeId)
        ->andReturn($expectedLeaveType);

    $result = $this->handler->handle($leaveTypeId);

    expect($result)
        ->toBe($expectedLeaveType)
        ->and($result->id->toString())->toBe($leaveTypeId);
});

it('returns null when leave type not found', function () {
    $leaveTypeId = '999e4567-e89b-12d3-a456-426614174000';

    $this->repository
        ->expects('findById')
        ->once()
        ->with($leaveTypeId)
        ->andReturn(null);

    $result = $this->handler->handle($leaveTypeId);

    expect($result)->toBeNull();
});
