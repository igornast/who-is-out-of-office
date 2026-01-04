<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Command\UpdateLeaveRequestCommandHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);

    $this->handler = new UpdateLeaveRequestCommandHandler(repository: $this->repository);
});

it('updates leave request', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('123e4567-e89b-12d3-a456-426614174000'),
        'status' => LeaveRequestStatusEnum::Pending,
    ]);

    $this->repository
        ->expects('update')
        ->once()
        ->with($leaveRequestDTO);

    $this->handler->handle($leaveRequestDTO);
});

it('updates leave request with approved status and approver', function () {
    $approver = UserDTOFixture::create(['id' => 'approver-123']);

    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('987e6543-e21b-12d3-a456-426614174999'),
        'status' => LeaveRequestStatusEnum::Approved,
        'approvedBy' => $approver,
    ]);

    $this->repository
        ->expects('update')
        ->once()
        ->with($leaveRequestDTO);

    $this->handler->handle($leaveRequestDTO);
});

it('updates leave request with rejected status', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('111e2222-e33b-44d5-a666-777777777777'),
        'status' => LeaveRequestStatusEnum::Rejected,
    ]);

    $this->repository
        ->expects('update')
        ->once()
        ->with($leaveRequestDTO);

    $this->handler->handle($leaveRequestDTO);
});
